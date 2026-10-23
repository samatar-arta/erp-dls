<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModuleRh;
use App\Enums\WorkflowAction;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Models\Employe;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowHistory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Seul point d'entrée des changements de statut. Chaque transition :
 *  - est vérifiée contre la matrice de WorkflowAction, après verrouillage de la ligne workflow ;
 *  - contrôle l'acteur (actif, permission, périmètre, séparation des responsabilités) ;
 *  - écrit l'historique dans la même transaction ;
 *  - à l'approbation, attribue la référence (et seulement là).
 */
class WorkflowService
{
    public function __construct(
        private readonly ReferenceService $references,
        private readonly PerimetreService $perimetre,
    ) {}

    public function executer(WorkflowAction $action, Model $objet, ?User $acteur, ?string $commentaire = null): Workflow
    {
        $commentaire = $commentaire !== null ? trim($commentaire) : null;
        $commentaire = $commentaire === '' ? null : $commentaire;

        if ($action->exigeCommentaire() && $commentaire === null) {
            throw new RegleMetierException('Un commentaire est obligatoire pour cette action.');
        }

        $workflow = DB::transaction(function () use ($action, $objet, $acteur, $commentaire): Workflow {
            // Verrou puis relecture : deux actions simultanées sur le même enregistrement sont sérialisées.
            $workflow = Workflow::query()
                ->where('workflowable_type', $objet->getMorphClass())
                ->where('workflowable_id', $objet->getKey())
                ->lockForUpdate()
                ->first();

            if ($workflow === null) {
                throw new RegleMetierException('Aucun workflow n\'est rattaché à cet enregistrement.');
            }

            $courant = $workflow->statut;

            if (! in_array($courant, $action->depuis(), true)) {
                throw new RegleMetierException(sprintf(
                    'Transition impossible : « %s » depuis le statut « %s ».',
                    $action->label(),
                    $courant->label(),
                ));
            }

            $this->autoriser($action, $workflow, $objet, $acteur);

            $this->appliquer($action, $workflow, $objet, $acteur);

            $workflow->forceFill(['statut' => $action->vers()]);
            $workflow->forceFill(['echeance_at' => $this->nouvelleEcheance($action->vers())]);
            $workflow->save();

            $historique = new WorkflowHistory;
            $historique->forceFill([
                'workflow_id' => $workflow->id,
                'action' => $action->value,
                'de_statut' => $courant->value,
                'vers_statut' => $action->vers()->value,
                'user_id' => $acteur?->getKey(),
                'commentaire' => $commentaire,
            ])->save();

            return $workflow;
        });

        $objet->unsetRelation('workflow');
        $objet->refresh();

        return $workflow;
    }

    public function soumettre(Model $objet, User $acteur, ?string $commentaire = null): Workflow
    {
        return $this->executer(WorkflowAction::Soumettre, $objet, $acteur, $commentaire);
    }

    public function prendreEnCharge(Model $objet, User $acteur, ?string $commentaire = null): Workflow
    {
        return $this->executer(WorkflowAction::PrendreEnCharge, $objet, $acteur, $commentaire);
    }

    public function valider(Model $objet, User $acteur, ?string $commentaire = null): Workflow
    {
        return $this->executer(WorkflowAction::Valider, $objet, $acteur, $commentaire);
    }

    public function transmettre(Model $objet, User $acteur, ?string $commentaire = null): Workflow
    {
        return $this->executer(WorkflowAction::Transmettre, $objet, $acteur, $commentaire);
    }

    public function approuver(Model $objet, User $acteur, ?string $commentaire = null): Workflow
    {
        return $this->executer(WorkflowAction::Approuver, $objet, $acteur, $commentaire);
    }

    public function rejeter(Model $objet, User $acteur, string $commentaire): Workflow
    {
        return $this->executer(WorkflowAction::Rejeter, $objet, $acteur, $commentaire);
    }

    public function rouvrir(Model $objet, User $acteur, ?string $commentaire = null): Workflow
    {
        return $this->executer(WorkflowAction::Rouvrir, $objet, $acteur, $commentaire);
    }

    /**
     * Passe à EXPIRE les enregistrements dont l'échéance est dépassée (appelé par le planificateur, Phase 8).
     *
     * @return int nombre d'enregistrements expirés
     */
    public function expirerEchus(): int
    {
        $expires = 0;

        Workflow::query()
            ->whereIn('statut', array_map(static fn (WorkflowStatut $s): string => $s->value, array_filter(
                WorkflowStatut::cases(),
                static fn (WorkflowStatut $s): bool => $s->estEnAttente(),
            )))
            ->where('echeance_at', '<', Carbon::now())
            ->orderBy('id')
            ->each(function (Workflow $workflow) use (&$expires): void {
                $objet = $workflow->workflowable;
                if ($objet === null) {
                    return;
                }

                try {
                    $this->executer(WorkflowAction::Expirer, $objet, null, 'Délai de traitement dépassé.');
                    $expires++;
                } catch (RegleMetierException) {
                    // Traité entre-temps par quelqu'un d'autre : rien à faire.
                }
            });

        return $expires;
    }

    /** Vrai si l'acteur pourrait exécuter l'action maintenant (pour afficher ou masquer un bouton). */
    public function peutExecuter(WorkflowAction $action, Model $objet, User $acteur): bool
    {
        $workflow = Workflow::query()
            ->where('workflowable_type', $objet->getMorphClass())
            ->where('workflowable_id', $objet->getKey())
            ->first();

        if ($workflow === null || ! in_array($workflow->statut, $action->depuis(), true)) {
            return false;
        }

        try {
            $this->autoriser($action, $workflow, $objet, $acteur);
        } catch (AuthorizationException|RegleMetierException) {
            return false;
        }

        return true;
    }

    private function autoriser(WorkflowAction $action, Workflow $workflow, Model $objet, ?User $acteur): void
    {
        if ($action->estSysteme()) {
            if ($acteur !== null) {
                throw new AuthorizationException('Cette action est réservée au système.');
            }

            return;
        }

        if ($acteur === null || ! $acteur->is_active) {
            throw new AuthorizationException('Utilisateur inactif ou non identifié.');
        }

        $permission = $action->permission($workflow->statut);
        if ($permission !== null && ! $acteur->can($permission)) {
            throw new AuthorizationException('Vous n\'avez pas le droit d\'effectuer cette action.');
        }

        $employe = $objet->getRelationValue('employe') ?? $objet->employe()->first();
        if (! $employe instanceof Employe || ! $this->perimetre->peutAccederEmploye($acteur, $employe)) {
            throw new AuthorizationException('Cet agent est hors de votre périmètre.');
        }

        // Droit de lecture propre au module (ex. sanctions à accès renforcé).
        if (! Gate::forUser($acteur)->allows('view', $objet)) {
            throw new AuthorizationException('Vous n\'avez pas accès à cet enregistrement.');
        }

        // Séparation des responsabilités : valable pour tous les rôles, ADMIN et RH_MANAGER compris.
        if ($action->appliqueSeparation()
            && in_array($acteur->getKey(), array_filter([$workflow->cree_par_id, $workflow->soumis_par_id]), true)) {
            throw new RegleMetierException('Séparation des responsabilités : vous ne pouvez pas traiter un enregistrement que vous avez créé ou soumis.');
        }

        if ($action === WorkflowAction::Approuver
            && config('workflow.approbateur_different_validateur')
            && $workflow->valide_par_id !== null
            && (int) $workflow->valide_par_id === (int) $acteur->getKey()) {
            throw new RegleMetierException('Séparation des responsabilités : l\'approbateur doit être différent du validateur.');
        }
    }

    private function appliquer(WorkflowAction $action, Workflow $workflow, Model $objet, ?User $acteur): void
    {
        switch ($action) {
            case WorkflowAction::Soumettre:
                $workflow->forceFill(['soumis_par_id' => $acteur?->getKey()]);
                break;
            case WorkflowAction::Valider:
                $workflow->forceFill(['valide_par_id' => $acteur?->getKey()]);
                break;
            case WorkflowAction::Rejeter:
                $workflow->forceFill(['rejete_par_id' => $acteur?->getKey()]);
                break;
            case WorkflowAction::Rouvrir:
                // Nouveau cycle : l'auteur reste, les acteurs du cycle précédent sont conservés dans l'historique.
                $workflow->forceFill(['soumis_par_id' => null, 'valide_par_id' => null, 'rejete_par_id' => null]);
                break;
            case WorkflowAction::Approuver:
                $workflow->forceFill(['approuve_par_id' => $acteur?->getKey()]);
                $this->attribuerReference($objet);
                break;
            default:
                break;
        }
    }

    /** La référence naît ici, à l'approbation finale, dans la transaction de l'approbation. */
    private function attribuerReference(Model $objet): void
    {
        if ($objet->getAttribute('reference') !== null) {
            return;
        }

        $module = ModuleRh::pourEnregistrement($objet);
        $objet->forceFill(['reference' => $this->references->attribuer($module)])->save();
    }

    private function nouvelleEcheance(WorkflowStatut $statut): ?Carbon
    {
        return $statut->estEnAttente()
            ? Carbon::now()->addHours((int) config('workflow.delai_heures', 48))
            : null;
    }
}
