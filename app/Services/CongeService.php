<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModeCalcul;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Models\Conge;
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

    /**
     * Jours décomptés, ventilés par année civile.
     *
     * @return array<int, float>
     */
    public function joursParAnnee(TypeConge $type, Carbon $debut, Carbon $fin): array
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
            $parAnnee[$jour->year] = ($parAnnee[$jour->year] ?? 0) + 1.0;
        }

        return $parAnnee;
    }

    public function calculerJours(TypeConge $type, Carbon $debut, Carbon $fin): float
    {
        return array_sum($this->joursParAnnee($type, $debut, $fin));
    }

    /**
     * Contrôles et calcul à la création / modification d'un brouillon : renvoie le nombre de jours décompté.
     */
    public function preparer(string $matricule, TypeConge $type, Carbon $debut, Carbon $fin, ?int $ignorerCongeId = null): float
    {
        $parAnnee = $this->joursParAnnee($type, $debut, $fin);
        $total = array_sum($parAnnee);

        if ($total <= 0) {
            throw new RegleMetierException('La période choisie ne contient aucun jour décompté (week-ends et jours fériés exclus).');
        }

        $this->verifierChevauchement($matricule, $debut, $fin, $ignorerCongeId);
        $this->verifierSolde($matricule, $type, $parAnnee, $ignorerCongeId);

        return $total;
    }

    /** Contrôle repris à la soumission : un autre congé a pu consommer le solde entre-temps. */
    public function verifierPourSoumission(Conge $conge): void
    {
        $type = $conge->type;
        $parAnnee = $this->joursParAnnee($type, $conge->date_debut, $conge->date_fin);

        $this->verifierChevauchement($conge->matricule, $conge->date_debut, $conge->date_fin, (int) $conge->getKey());
        $this->verifierSolde($conge->matricule, $type, $parAnnee, (int) $conge->getKey());
    }

    /**
     * À l'approbation : débit du solde sous verrou. Refuse si le solde réel est insuffisant (rollback du workflow).
     */
    public function debiterALApprobation(Conge $conge): void
    {
        $type = $conge->type;

        if (! $this->estControle($type)) {
            return;
        }

        foreach ($this->joursParAnnee($type, $conge->date_debut, $conge->date_fin) as $annee => $jours) {
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

    /** Solde de l'agent pour l'année (sans écriture : valeur théorique si la ligne n'existe pas encore). */
    public function soldeRestant(string $matricule, TypeConge $type, int $annee): float
    {
        $solde = SoldeConge::query()
            ->where(['matricule' => $matricule, 'type_conge_id' => $type->getKey(), 'annee' => $annee])
            ->first();

        return $solde?->restant() ?? (float) $type->default_days_per_year;
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
                $total += $this->joursParAnnee($type, $autre->date_debut, $autre->date_fin)[$annee] ?? 0.0;
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
            [$matricule, $type->getKey(), $annee, (float) $type->default_days_per_year, $maintenant, $maintenant],
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
