<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QualificationConge;
use App\Enums\WorkflowStatut;
use App\Models\Conge;
use App\Models\SoldeConge;
use App\Models\TypeConge;
use Illuminate\Support\Facades\DB;

/**
 * Reprise de l'historique des congés annuels sous les règles DLS (Phase 12) :
 * qualification rétroactive, puis recalcul de `jours_pris` des soldes.
 * Sans écriture tant que $appliquer est faux (simulation).
 */
class HistoriqueCongesService
{
    public function __construct(private readonly CongeService $conges, private readonly AuditService $audit) {}

    /**
     * Attribue la qualification aux congés ANNUEL qui n'en ont pas. Un congé historique de moins de 5 jours
     * (non conforme à la règle actuelle) est qualifié « autorisation d'absence » et signalé, jamais modifié ni annulé.
     *
     * @return array{total: int, titres: int, autorisations: int, non_conformes: list<array{id: int, matricule: string, jours: float}>}
     */
    public function qualifier(bool $appliquer): array
    {
        $bilan = ['total' => 0, 'titres' => 0, 'autorisations' => 0, 'non_conformes' => []];

        DB::transaction(function () use ($appliquer, &$bilan): void {
            $this->congesAnnuelsSansQualification()->each(function (Conge $conge) use ($appliquer, &$bilan): void {
                $jours = $this->conges->joursCalendaires($conge->date_debut, $conge->date_fin, (bool) $conge->demi_debut, (bool) $conge->demi_fin);
                $qualification = QualificationConge::pourDuree($jours);

                $bilan['total']++;
                $bilan[$qualification === QualificationConge::TitreConge ? 'titres' : 'autorisations']++;

                if ($qualification === QualificationConge::AutorisationAbsence && $jours < QualificationConge::SEUIL_MINIMAL_AUTORISATION) {
                    $bilan['non_conformes'][] = ['id' => (int) $conge->getKey(), 'matricule' => $conge->matricule, 'jours' => $jours];
                }

                if ($appliquer) {
                    // Requête directe : la qualification d'un dossier déjà traité ne passe pas par les gardes du modèle.
                    DB::table('conges')->where('id', $conge->getKey())->update(['qualification' => $qualification->value]);
                }
            });

            if ($appliquer && $bilan['total'] > 0) {
                $this->audit->journaliser('conge.historique_qualifie', null, "{$bilan['total']} congé(s) annuel(s) qualifié(s) rétroactivement.", [
                    'titres' => $bilan['titres'],
                    'autorisations' => $bilan['autorisations'],
                    'non_conformes' => count($bilan['non_conformes']),
                ]);
            }
        });

        return $bilan;
    }

    /**
     * Recalcule `jours_pris` de chaque solde ANNUEL à partir des congés approuvés effectivement déduits
     * (règles de déduction actuelles). Seul `jours_pris` est touché ; chaque correction est tracée par le modèle.
     *
     * @return array{verifies: int, ecarts: list<array{matricule: string, annee: int, actuel: float, attendu: float, restant: float}>, sans_solde: list<array{matricule: string, annee: int, attendu: float}>}
     */
    public function recalculerSoldes(bool $appliquer): array
    {
        $type = TypeConge::query()->where('code', TypeConge::CODE_ANNUEL)->first();
        $bilan = ['verifies' => 0, 'ecarts' => [], 'sans_solde' => []];

        if ($type === null) {
            return $bilan;
        }

        /** @var array<string, array<int, float>> $attendus matricule => année => jours */
        $attendus = [];

        Conge::query()
            ->with(['employe.departement.societe', 'employe.societe'])
            ->where('type_conge_id', $type->getKey())
            ->whereHas('workflow', fn ($q) => $q->where('statut', WorkflowStatut::Approuve->value))
            ->each(function (Conge $conge) use ($type, &$attendus): void {
                $qualification = $conge->qualification
                    ?? QualificationConge::pourDuree($this->conges->joursCalendaires($conge->date_debut, $conge->date_fin, (bool) $conge->demi_debut, (bool) $conge->demi_fin));

                $attendus[$conge->matricule] ??= [];
                $parAnnee = $this->conges->joursParAnnee($type, $conge->date_debut, $conge->date_fin, (bool) $conge->demi_debut, (bool) $conge->demi_fin);

                if ($this->conges->deduitDuSolde($conge->employe, $type, $qualification, (bool) $conge->pendant_heures_travail)) {
                    foreach ($parAnnee as $annee => $jours) {
                        $attendus[$conge->matricule][$annee] = ($attendus[$conge->matricule][$annee] ?? 0.0) + $jours;
                    }
                }
            });

        DB::transaction(function () use ($appliquer, $type, $attendus, &$bilan): void {
            $soldes = SoldeConge::query()->where('type_conge_id', $type->getKey())->lockForUpdate()->get();
            $vus = [];

            foreach ($soldes as $solde) {
                $attendu = round($attendus[$solde->matricule][$solde->annee] ?? 0.0, 1);
                $actuel = round((float) $solde->jours_pris, 1);
                $vus[$solde->matricule][$solde->annee] = true;
                $bilan['verifies']++;

                if (abs($attendu - $actuel) < 0.05) {
                    continue;
                }

                $bilan['ecarts'][] = [
                    'matricule' => $solde->matricule,
                    'annee' => $solde->annee,
                    'actuel' => $actuel,
                    'attendu' => $attendu,
                    'restant' => (float) $solde->jours_acquis + (float) $solde->jours_report - $attendu,
                ];

                if ($appliquer) {
                    $solde->forceFill(['jours_pris' => $attendu])->save();
                }
            }

            // Jours déduits sans ligne de solde : signalés, jamais créés ici.
            foreach ($attendus as $matricule => $parAnnee) {
                foreach ($parAnnee as $annee => $jours) {
                    if (! isset($vus[$matricule][$annee]) && $jours > 0) {
                        $bilan['sans_solde'][] = ['matricule' => $matricule, 'annee' => $annee, 'attendu' => round($jours, 1)];
                    }
                }
            }
        });

        return $bilan;
    }

    private function congesAnnuelsSansQualification(): \Illuminate\Support\LazyCollection
    {
        return Conge::query()
            ->whereNull('qualification')
            ->whereHas('type', fn ($q) => $q->where('code', TypeConge::CODE_ANNUEL))
            ->orderBy('id')
            ->lazyById();
    }
}
