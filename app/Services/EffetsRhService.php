<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Models\Employe;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Effet des promotions et mutations approuvées sur l'affectation de l'agent, via EmployeService
 * (historique conservé). Appliqué à l'approbation si la date d'effet est échue, sinon par appliquerEchus()
 * (commande dls:appliquer-effets, à planifier en Phase 8). Un effet n'est jamais appliqué deux fois.
 */
class EffetsRhService
{
    public function __construct(private readonly EmployeService $employes)
    {
    }

    /** Appelé dans la transaction de l'approbation : une erreur annule l'approbation entière. */
    public function appliquerALApprobation(Model $objet, ?User $acteur): void
    {
        if (! ($objet instanceof Promotion || $objet instanceof Mutation)) {
            return;
        }

        if ($objet->date_effet->gt(Carbon::today())) {
            return; // différé : appliquerEchus() s'en chargera à la date d'effet
        }

        $this->appliquer($objet, $acteur);
    }

    /** @return bool vrai si l'effet vient d'être appliqué, faux s'il l'était déjà */
    public function appliquer(Promotion|Mutation $objet, ?User $acteur = null): bool
    {
        return DB::transaction(function () use ($objet, $acteur): bool {
            $objet = $objet->newQuery()->lockForUpdate()->findOrFail($objet->getKey());

            if ($objet->appliquee_le !== null) {
                return false;
            }

            $employe = Employe::query()->where('matricule', $objet->matricule)->firstOrFail();

            if ($employe->statut_employe !== 'actif') {
                throw new RegleMetierException('Cet agent n\'est plus en activité : l\'effet ne peut pas être appliqué.');
            }

            $cible = $objet instanceof Mutation
                ? $this->cibleMutation($objet, $employe)
                : $this->ciblePromotion($objet, $employe);

            if ($cible !== null) {
                $this->employes->changerAffectation(
                    $employe,
                    $cible,
                    $objet->date_effet,
                    $objet instanceof Mutation ? 'mutation' : 'promotion',
                    $acteur,
                );
            }

            $objet->forceFill(['appliquee_le' => now()])->save();

            return true;
        });
    }

    /**
     * Applique les effets échus des enregistrements approuvés, par ordre de date d'effet.
     *
     * @return int nombre d'effets appliqués
     */
    public function appliquerEchus(): int
    {
        $appliques = 0;

        foreach ([Mutation::class, Promotion::class] as $classe) {
            $classe::query()
                ->whereNull('appliquee_le')
                ->whereDate('date_effet', '<=', Carbon::today()->toDateString())
                ->whereHas('workflow', fn ($q) => $q->where('statut', WorkflowStatut::Approuve->value))
                ->orderBy('date_effet')
                ->orderBy('id')
                ->each(function (Model $objet) use (&$appliques): void {
                    try {
                        $this->appliquer($objet) && $appliques++;
                    } catch (RegleMetierException $e) {
                        Log::warning("Effet non appliqué ({$objet->getMorphClass()} #{$objet->getKey()}) : {$e->getMessage()}");
                    }
                });
        }

        return $appliques;
    }

    /** @return array{societe_id: int, site_id: int, departement_id: int, fonction: ?string} */
    private function cibleMutation(Mutation $mutation, Employe $employe): array
    {
        return [
            'societe_id' => (int) ($mutation->nouvelle_societe_id ?? $employe->societe_id),
            'site_id' => (int) ($mutation->nouveau_site_id ?? $employe->site_id),
            'departement_id' => (int) ($mutation->nouveau_departement_id ?? $employe->departement_id),
            'fonction' => $mutation->nouvelle_fonction !== null && $mutation->nouvelle_fonction !== ''
                ? $mutation->nouvelle_fonction
                : $employe->fonction,
        ];
    }

    /**
     * Une promotion ne change que la fonction. Le grade et le salaire restent dans le dossier de promotion
     * (l'agent n'a ni grade courant ni salaire).
     *
     * @return ?array{societe_id: int, site_id: int, departement_id: int, fonction: ?string}
     */
    private function ciblePromotion(Promotion $promotion, Employe $employe): ?array
    {
        if ($promotion->nouvelle_fonction === null || $promotion->nouvelle_fonction === '' || $promotion->nouvelle_fonction === $employe->fonction) {
            return null;
        }

        return [
            'societe_id' => (int) $employe->societe_id,
            'site_id' => (int) $employe->site_id,
            'departement_id' => (int) $employe->departement_id,
            'fonction' => $promotion->nouvelle_fonction,
        ];
    }
}
