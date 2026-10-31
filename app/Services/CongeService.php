<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModeCalcul;
use App\Enums\MotifPermission;
use App\Enums\QualificationConge;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Models\Conge;
use App\Models\Employe;
use App\Models\JourFerie;
use App\Models\SoldeConge;
use App\Models\TypeConge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Calcul des jours de congé (calendaires ou ouvrés, jours fériés exclus), chevauchements et soldes.
 * Le solde n'est débité qu'à l'approbation finale, dans la transaction du workflow.
 */
class CongeService
{
    /** Statuts dont les jours sont « engagés » sans être encore débités. */
    private const STATUTS_ENGAGES = [
        WorkflowStatut::DocumentSoumis,
        WorkflowStatut::EnAttenteValidation,
        WorkflowStatut::Valide,
        WorkflowStatut::EnAttenteApprobation,
    ];

    /** Statuts dont les jours comptent pour le plafond des permissions : en circuit ou approuvés. */
    private const STATUTS_ENGAGES_ET_APPROUVE = [
        WorkflowStatut::DocumentSoumis,
        WorkflowStatut::EnAttenteValidation,
        WorkflowStatut::Valide,
        WorkflowStatut::EnAttenteApprobation,
        WorkflowStatut::Approuve,
    ];

    /**
     * Jours décomptés, ventilés par année civile.
     *
     * @return array<int, float>
     */
    public function joursParAnnee(TypeConge $type, Carbon $debut, Carbon $fin, bool $demiDebut = false, bool $demiFin = false): array
    {
        if ($fin->lt($debut)) {
            throw new RegleMetierException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        $ouvre = $type->mode_calcul === ModeCalcul::Ouvre;
        $feries = $ouvre ? $this->joursFeries() : [];
        $nonOuvres = config('conges.jours_non_ouvres', []);
        $parAnnee = [];

        for ($jour = $debut->copy()->startOfDay(); $jour->lte($fin); $jour->addDay()) {
            if ($ouvre && (in_array($jour->dayOfWeekIso, $nonOuvres, true) || $this->estFerie($jour, $feries))) {
                continue;
            }
            // Demi-journée : le premier et/ou le dernier jour ne compte que pour 0,5 (une seule déduction si c'est le même jour).
            $demi = ($demiDebut && $jour->isSameDay($debut)) || ($demiFin && $jour->isSameDay($fin));
            $parAnnee[$jour->year] = ($parAnnee[$jour->year] ?? 0) + ($demi ? 0.5 : 1.0);
        }

        return $parAnnee;
    }

    public function calculerJours(TypeConge $type, Carbon $debut, Carbon $fin, bool $demiDebut = false, bool $demiFin = false): float
    {
        return array_sum($this->joursParAnnee($type, $debut, $fin, $demiDebut, $demiFin));
    }

    /**
     * Contrôles et calcul à la création / modification d'un brouillon : renvoie le nombre de jours décompté.
     */
    public function preparer(
        string $matricule,
        TypeConge $type,
        Carbon $debut,
        Carbon $fin,
        ?int $ignorerCongeId = null,
        bool $demiDebut = false,
        bool $demiFin = false,
        bool $pendantHeuresTravail = false,
        ?MotifPermission $motif = null,
    ): float {
        $parAnnee = $this->joursParAnnee($type, $debut, $fin, $demiDebut, $demiFin);
        $total = array_sum($parAnnee);

        if ($total <= 0) {
            throw new RegleMetierException('La période choisie ne contient aucun jour décompté (week-ends et jours fériés exclus).');
        }

        $qualification = $this->qualifier($type, $debut, $fin, $demiDebut, $demiFin);

        $this->verifierPermission($matricule, $type, $motif, $total, $debut, $ignorerCongeId, self::STATUTS_ENGAGES_ET_APPROUVE);
        $this->verifierChevauchement($matricule, $debut, $fin, $ignorerCongeId);

        if ($this->deduitDuSolde($this->employe($matricule), $type, $qualification, $pendantHeuresTravail)) {
            $this->verifierSolde($matricule, $type, $parAnnee, $ignorerCongeId);
        }

        return $total;
    }

    /** Contrôle repris à la soumission : un autre congé a pu consommer le solde ou le crédit de permissions entre-temps. */
    public function verifierPourSoumission(Conge $conge): void
    {
        $type = $conge->type;
        $demiDebut = (bool) $conge->demi_debut;
        $demiFin = (bool) $conge->demi_fin;
        $parAnnee = $this->joursParAnnee($type, $conge->date_debut, $conge->date_fin, $demiDebut, $demiFin);
        $qualification = $this->qualifier($type, $conge->date_debut, $conge->date_fin, $demiDebut, $demiFin);

        $this->verifierPermission($conge->matricule, $type, $conge->motif_permission, array_sum($parAnnee), $conge->date_debut, (int) $conge->getKey(), self::STATUTS_ENGAGES_ET_APPROUVE);
        $this->verifierChevauchement($conge->matricule, $conge->date_debut, $conge->date_fin, (int) $conge->getKey());

        if ($this->deduitDuSolde($this->employe($conge->matricule), $type, $qualification, (bool) $conge->pendant_heures_travail)) {
            $this->verifierSolde($conge->matricule, $type, $parAnnee, (int) $conge->getKey());
        }
    }

    /**
     * À l'approbation : débit du solde sous verrou. Refuse si le solde réel est insuffisant (rollback du workflow).
     * Une permission exceptionnelle ne débite jamais le solde mais respecte le plafond annuel.
     */
    public function debiterALApprobation(Conge $conge): void
    {
        $type = $conge->type;
        $demiDebut = (bool) $conge->demi_debut;
        $demiFin = (bool) $conge->demi_fin;
        $parAnnee = $this->joursParAnnee($type, $conge->date_debut, $conge->date_fin, $demiDebut, $demiFin);

        if ($type->estPermissionExceptionnelle()) {
            // Verrou sur l'agent : deux approbations simultanées ne peuvent pas dépasser le plafond ensemble.
            Employe::query()->where('matricule', $conge->matricule)->lockForUpdate()->first();
            $this->verifierPermission($conge->matricule, $type, $conge->motif_permission, array_sum($parAnnee), $conge->date_debut, (int) $conge->getKey(), [WorkflowStatut::Approuve]);

            return;
        }

        $qualification = $this->qualifier($type, $conge->date_debut, $conge->date_fin, $demiDebut, $demiFin);

        if (! $this->deduitDuSolde($this->employe($conge->matricule), $type, $qualification, (bool) $conge->pendant_heures_travail)) {
            return;
        }

        foreach ($parAnnee as $annee => $jours) {
            $solde = $this->soldeVerrouille($conge->matricule, $type, $annee);

            if ($jours > $solde->restant() + 1e-9) {
                throw new RegleMetierException(sprintf(
                    'Solde insuffisant en %d : %s jour(s) restant(s), %s demandé(s).',
                    $annee,
                    $this->nombre($solde->restant()),
                    $this->nombre($jours),
                ));
            }

            $solde->forceFill(['jours_pris' => (float) $solde->jours_pris + $jours])->save();
        }
    }

    /** Durée en jours calendaires (demi-journées comprises), indépendante du mode de calcul du type. */
    public function joursCalendaires(Carbon $debut, Carbon $fin, bool $demiDebut = false, bool $demiFin = false): float
    {
        $jours = (float) ($debut->copy()->startOfDay()->diffInDays($fin->copy()->startOfDay(), true) + 1);

        if ($debut->isSameDay($fin)) {
            return ($demiDebut || $demiFin) ? $jours - 0.5 : $jours;
        }

        return $jours - ($demiDebut ? 0.5 : 0.0) - ($demiFin ? 0.5 : 0.0);
    }

    /**
     * Titre de congé (≥ 30 jours calendaires) ou autorisation d'absence (5 à 29 jours) pour le type ANNUEL ;
     * null pour les autres types. Lève une exception si une autorisation d'absence dure moins de 5 jours.
     */
    public function qualifier(TypeConge $type, Carbon $debut, Carbon $fin, bool $demiDebut = false, bool $demiFin = false): ?QualificationConge
    {
        if (! $type->estAnnuel()) {
            return null;
        }

        $jours = $this->joursCalendaires($debut, $fin, $demiDebut, $demiFin);
        $qualification = QualificationConge::pourDuree($jours);

        if ($qualification === QualificationConge::AutorisationAbsence && $jours < QualificationConge::SEUIL_MINIMAL_AUTORISATION) {
            throw new RegleMetierException(sprintf(
                'Une autorisation d\'absence doit durer au moins %d jours (de %d à %d jours) ; %s jour(s) demandé(s).',
                (int) QualificationConge::SEUIL_MINIMAL_AUTORISATION,
                (int) QualificationConge::SEUIL_MINIMAL_AUTORISATION,
                (int) QualificationConge::SEUIL_TITRE_CONGE - 1,
                $this->nombre($jours),
            ));
        }

        return $qualification;
    }

    /**
     * La demande est-elle déduite du solde de congé annuel ?
     * - permission exceptionnelle ou type sans droit annuel : jamais ;
     * - titre de congé et autorisation d'absence hors heures de travail : oui ;
     * - autorisation d'absence durant les heures de travail : seulement pour un agent DLS non mis à disposition d'un client.
     */
    public function deduitDuSolde(Employe $employe, TypeConge $type, ?QualificationConge $qualification, bool $pendantHeuresTravail): bool
    {
        if ($type->estPermissionExceptionnelle() || ! $this->estControle($type)) {
            return false;
        }

        if ($qualification === QualificationConge::AutorisationAbsence && $pendantHeuresTravail) {
            return $this->estAgentDls($employe) && $employe->mis_a_disposition_societe_id === null;
        }

        return true;
    }

    /** Agent rattaché à la société DLS (par son département, à défaut par sa société). */
    private function estAgentDls(Employe $employe): bool
    {
        $societe = $employe->departement?->societe ?? $employe->societe;

        return $societe !== null && $societe->code === config('conges.societe_dls', 'DLS');
    }

    private function employe(string $matricule): Employe
    {
        return Employe::query()->with(['departement.societe', 'societe'])->where('matricule', $matricule)->firstOrFail();
    }

    /**
     * Permission exceptionnelle : motif obligatoire, durée (jours ouvrés) dans le barème du motif, et crédit annuel
     * de 11 jours par agent (année civile de la date de début) non dépassé.
     *
     * @param  list<WorkflowStatut>  $statutsComptes  statuts des autres permissions qui consomment le crédit
     */
    private function verifierPermission(string $matricule, TypeConge $type, ?MotifPermission $motif, float $jours, Carbon $debut, ?int $ignorerCongeId, array $statutsComptes): void
    {
        if (! $type->estPermissionExceptionnelle()) {
            return;
        }

        if ($motif === null) {
            throw new RegleMetierException('Le motif de la permission exceptionnelle est obligatoire.');
        }

        if ($jours > $motif->jours() + 1e-9) {
            throw new RegleMetierException(sprintf(
                'Le motif « %s » ouvre droit à %s jour(s) ouvré(s) au plus ; %s demandé(s).',
                $motif->label(),
                $this->nombre($motif->jours()),
                $this->nombre($jours),
            ));
        }

        $consommes = (float) Conge::query()
            ->where(['matricule' => $matricule, 'type_conge_id' => $type->getKey()])
            ->whereYear('date_debut', $debut->year)
            ->when($ignorerCongeId !== null, fn ($q) => $q->whereKeyNot($ignorerCongeId))
            ->whereHas('workflow', fn ($q) => $q->whereIn('statut', array_map(fn (WorkflowStatut $s): string => $s->value, $statutsComptes)))
            ->sum('nbre_jours');

        if ($consommes + $jours > MotifPermission::PLAFOND_ANNUEL + 1e-9) {
            throw new RegleMetierException(sprintf(
                'Crédit de permissions exceptionnelles dépassé en %d : %s jour(s) déjà consommé(s) ou engagé(s) sur %d, %s demandé(s).',
                $debut->year,
                $this->nombre($consommes),
                (int) MotifPermission::PLAFOND_ANNUEL,
                $this->nombre($jours),
            ));
        }
    }

    /** Solde de l'agent pour l'année (sans écriture : valeur théorique si la ligne n'existe pas encore). */
    public function soldeRestant(string $matricule, TypeConge $type, int $annee): float
    {
        $solde = SoldeConge::query()
            ->where(['matricule' => $matricule, 'type_conge_id' => $type->getKey(), 'annee' => $annee])
            ->first();

        return $solde?->restant() ?? $this->droitInitial($matricule, $type, $annee);
    }

    /**
     * Droit annuel de l'année, au prorata de la date d'embauche (jours restants de l'année d'embauche, embauche
     * incluse, arrondis à la demi-journée la plus proche). Aucun droit avant l'année d'embauche ; pas de report automatique.
     */
    public function droitInitial(string $matricule, TypeConge $type, int $annee): float
    {
        $droit = (float) $type->default_days_per_year;
        $embauche = Employe::query()->where('matricule', $matricule)->value('date_embauche');

        if ($embauche === null) {
            return $droit;
        }

        $embauche = Carbon::parse($embauche)->startOfDay();

        if ($embauche->year > $annee) {
            return 0.0;
        }

        if ($embauche->year < $annee) {
            return $droit;
        }

        $joursAnnee = $embauche->isLeapYear() ? 366 : 365;
        $restants = $joursAnnee - $embauche->dayOfYear + 1;

        return round($droit * $restants / $joursAnnee * 2) / 2;
    }

    /** Seuls les types dotés d'un droit annuel sont soumis au solde. */
    public function estControle(TypeConge $type): bool
    {
        return (float) $type->default_days_per_year > 0;
    }

    public function verifierChevauchement(string $matricule, Carbon $debut, Carbon $fin, ?int $ignorerCongeId = null): void
    {
        $existant = Conge::query()
            ->where('matricule', $matricule)
            ->whereDate('date_debut', '<=', $fin->toDateString())
            ->whereDate('date_fin', '>=', $debut->toDateString())
            ->when($ignorerCongeId !== null, fn ($q) => $q->whereKeyNot($ignorerCongeId))
            ->whereHas('workflow', fn ($q) => $q->whereNotIn('statut', [WorkflowStatut::Rejete->value, WorkflowStatut::Expire->value]))
            ->orderBy('date_debut')
            ->first();

        if ($existant !== null) {
            throw new RegleMetierException(sprintf(
                'Cette période chevauche un congé existant de l\'agent (du %s au %s).',
                $existant->date_debut->format('d/m/Y'),
                $existant->date_fin->format('d/m/Y'),
            ));
        }
    }

    /**
     * @param  array<int, float>  $parAnnee
     */
    private function verifierSolde(string $matricule, TypeConge $type, array $parAnnee, ?int $ignorerCongeId): void
    {
        if (! $this->estControle($type)) {
            return;
        }

        foreach ($parAnnee as $annee => $jours) {
            $disponible = $this->soldeRestant($matricule, $type, $annee) - $this->joursEngages($matricule, $type, $annee, $ignorerCongeId);

            if ($jours > $disponible + 1e-9) {
                throw new RegleMetierException(sprintf(
                    'Solde insuffisant en %d : %s jour(s) disponible(s), %s demandé(s).',
                    $annee,
                    $this->nombre(max(0, $disponible)),
                    $this->nombre($jours),
                ));
            }
        }
    }

    /** Jours des congés en cours de circuit (hors brouillons) pour la même année et le même type. */
    private function joursEngages(string $matricule, TypeConge $type, int $annee, ?int $ignorerCongeId): float
    {
        $total = 0.0;
        $statuts = array_map(fn (WorkflowStatut $s): string => $s->value, self::STATUTS_ENGAGES);

        Conge::query()
            ->where(['matricule' => $matricule, 'type_conge_id' => $type->getKey()])
            ->when($ignorerCongeId !== null, fn ($q) => $q->whereKeyNot($ignorerCongeId))
            ->whereHas('workflow', fn ($q) => $q->whereIn('statut', $statuts))
            ->get()
            ->each(function (Conge $autre) use ($type, $annee, &$total): void {
                if (! $this->deduitDuSolde($this->employe($autre->matricule), $type, $autre->qualification, (bool) $autre->pendant_heures_travail)) {
                    return;
                }

                $total += $this->joursParAnnee($type, $autre->date_debut, $autre->date_fin, (bool) $autre->demi_debut, (bool) $autre->demi_fin)[$annee] ?? 0.0;
            });

        return $total;
    }

    /**
     * Crée la ligne du solde si besoin (instruction atomique, sans interblocage) puis la lit sous verrou.
     */
    private function soldeVerrouille(string $matricule, TypeConge $type, int $annee): SoldeConge
    {
        $maintenant = now();

        DB::statement(
            'INSERT INTO soldes_conges (matricule, type_conge_id, annee, jours_acquis, jours_report, jours_pris, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, 0, ?, ?)
             ON DUPLICATE KEY UPDATE jours_pris = jours_pris',
            [$matricule, $type->getKey(), $annee, $this->droitInitial($matricule, $type, $annee), $maintenant, $maintenant],
        );

        return SoldeConge::query()
            ->where(['matricule' => $matricule, 'type_conge_id' => $type->getKey(), 'annee' => $annee])
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return array{exacts: array<string, true>, recurrents: array<string, true>} */
    private function joursFeries(): array
    {
        $exacts = [];
        $recurrents = [];

        foreach (JourFerie::query()->where('is_active', true)->get() as $ferie) {
            $exacts[$ferie->date_jour->toDateString()] = true;
            if ($ferie->recurrent) {
                $recurrents[$ferie->date_jour->format('m-d')] = true;
            }
        }

        return ['exacts' => $exacts, 'recurrents' => $recurrents];
    }

    /** @param  array{exacts?: array<string, true>, recurrents?: array<string, true>}  $feries */
    private function estFerie(Carbon $jour, array $feries): bool
    {
        return isset($feries['exacts'][$jour->toDateString()]) || isset($feries['recurrents'][$jour->format('m-d')]);
    }

    private function nombre(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 1, ',', ''), '0'), ',');
    }
}
