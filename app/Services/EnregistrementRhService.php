<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModuleRh;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Création et modification des enregistrements des six modules (brouillons) : droits, périmètre,
 * champs reprenant la situation actuelle de l'agent. Les changements de statut passent par WorkflowService.
 */
class EnregistrementRhService
{
    public function __construct(private readonly PerimetreService $perimetre)
    {
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function creer(ModuleRh $module, array $donnees, User $acteur): Model
    {
        $classe = $module->enregistrementClass();

        if (! Gate::forUser($acteur)->allows('create', $classe)) {
            throw new AuthorizationException('Vous n\'avez pas le droit de créer cet enregistrement.');
        }

        $employe = $this->employeAccessible((string) ($donnees['matricule'] ?? ''), $acteur);

        if ($module !== ModuleRh::Document && $employe->statut_employe !== 'actif') {
            throw new RegleMetierException('Cet agent n\'est plus en activité.');
        }

        $donnees = $this->completer($module, $donnees, $employe, $acteur);

        return DB::transaction(function () use ($classe, $donnees): Model {
            $enregistrement = new $classe;
            $enregistrement->fill($donnees);
            $enregistrement->save();

            return $enregistrement;
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function mettreAJour(Model $enregistrement, array $donnees, User $acteur): Model
    {
        if (! Gate::forUser($acteur)->allows('update', $enregistrement)) {
            throw new AuthorizationException('Cet enregistrement n\'est plus modifiable.');
        }

        // L'agent concerné ne change jamais : on supprime un brouillon pour en refaire un autre.
        unset($donnees['matricule']);

        return DB::transaction(function () use ($enregistrement, $donnees): Model {
            $enregistrement = $enregistrement->newQuery()->lockForUpdate()->findOrFail($enregistrement->getKey());

            if ($enregistrement->workflow?->statut !== WorkflowStatut::Brouillon) {
                throw new RegleMetierException('Seul un brouillon peut être modifié.');
            }

            $enregistrement->fill($donnees)->save();

            return $enregistrement;
        });
    }

    private function employeAccessible(string $matricule, User $acteur): Employe
    {
        $employe = Employe::query()->where('matricule', $matricule)->first();

        if ($employe === null || ! $this->perimetre->peutAccederEmploye($acteur, $employe)) {
            throw new AuthorizationException('Cet agent est introuvable ou hors de votre périmètre.');
        }

        return $employe;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function completer(ModuleRh $module, array $donnees, Employe $employe, User $acteur): array
    {
        // Les champs « situation actuelle » ne sont jamais saisis : ils sont lus sur l'agent.
        switch ($module) {
            case ModuleRh::Document:
            case ModuleRh::Sanction:
                $donnees['cree_par'] = $acteur->getKey();
                break;
            case ModuleRh::Conge:
                $donnees['user_id'] = $acteur->getKey();
                break;
            case ModuleRh::Evaluation:
                $donnees['evaluateur_id'] ??= $acteur->getKey();
                break;
            case ModuleRh::Promotion:
                $donnees['cree_par'] = $acteur->getKey();
                $donnees['ancienne_fonction'] = $employe->fonction;
                if (! $acteur->can(\App\Support\Permissions::SALAIRES_VIEW)) {
                    unset($donnees['ancien_salaire'], $donnees['nouveau_salaire']);
                }
                break;
            case ModuleRh::Mutation:
                $donnees = $this->completerMutation($donnees, $employe, $acteur);
                break;
        }

        return $donnees;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function completerMutation(array $donnees, Employe $employe, User $acteur): array
    {
        $donnees['cree_par'] = $acteur->getKey();
        $donnees['ancien_site_id'] = $employe->site_id;
        $donnees['ancien_departement_id'] = $employe->departement_id;
        $donnees['ancienne_societe_id'] = $employe->societe_id;
        $donnees['ancienne_fonction'] = $employe->fonction;

        // Un département nouveau sans société explicite reste dans la société actuelle.
        if (! empty($donnees['nouveau_departement_id'])) {
            $donnees['nouvelle_societe_id'] ??= $employe->societe_id;
        }

        $changements = array_filter([
            $donnees['nouveau_site_id'] ?? null,
            $donnees['nouveau_departement_id'] ?? null,
            $donnees['nouvelle_societe_id'] ?? null,
            $donnees['nouvelle_fonction'] ?? null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        if ($changements === []) {
            throw new RegleMetierException('Une mutation doit changer au moins le site, le département, la société ou la fonction.');
        }

        // Le nouveau couple site / département doit exister et rester dans le périmètre de l'acteur.
        $site = (int) ($donnees['nouveau_site_id'] ?? $employe->site_id);
        $departement = (int) ($donnees['nouveau_departement_id'] ?? $employe->departement_id);
        $societe = (int) ($donnees['nouvelle_societe_id'] ?? $employe->societe_id);
        app(EmployeService::class)->verifierAffectationAutorisee($acteur, $societe, $site, $departement);

        return $donnees;
    }
}
