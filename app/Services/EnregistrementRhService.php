<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModuleRh;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Models\Conge;
use App\Models\Document;
use App\Models\Employe;
use App\Models\Evaluation;
use App\Models\ModelEvaluation;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\TypeConge;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Création et modification des enregistrements des six modules (brouillons) : droits, périmètre,
 * champs reprenant la situation actuelle de l'agent. Les changements de statut passent par WorkflowService.
 */
class EnregistrementRhService
{
    public function __construct(
        private readonly PerimetreService $perimetre,
        private readonly CongeService $conges,
        private readonly EvaluationService $evaluations,
    ) {
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

        if ($module === ModuleRh::Document) {
            $this->verifierDelaiRenouvellement($donnees, $employe);
        }

        $donnees = $this->completer($module, $donnees, $employe, $acteur);

        return DB::transaction(function () use ($classe, $donnees): Model {
            $enregistrement = new $classe;
            $this->remplir($enregistrement, $donnees)->save();

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

            if ($enregistrement instanceof Conge) {
                // Le nombre de jours est toujours recalculé par le serveur à partir des dates et du type.
                $donnees['nbre_jours'] = $this->calculerJoursConge(
                    $donnees + $enregistrement->only(['type_conge_id', 'date_debut', 'date_fin', 'demi_debut', 'demi_fin']),
                    $enregistrement->matricule,
                    (int) $enregistrement->getKey(),
                );
            }

            if ($enregistrement instanceof Evaluation) {
                // Notes et note finale : recalculées sur le modèle retenu ; les notes déjà saisies servent de base.
                $donnees = $this->noterEvaluation($donnees + $enregistrement->only(['model_evaluation_id', 'notes_criteres']));
            }

            if ($enregistrement instanceof Promotion || $enregistrement instanceof Mutation) {
                $this->verifierDateEffet($enregistrement->employe, $donnees['date_effet'] ?? $enregistrement->date_effet);
            }

            $this->remplir($enregistrement, $donnees)->save();

            return $enregistrement;
        });
    }

    /**
     * Un type de document peut imposer un délai avant d'en produire un nouveau pour le même agent.
     * Les documents rejetés ou expirés ne comptent pas.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function verifierDelaiRenouvellement(array $donnees, Employe $employe): void
    {
        $type = TypeDocument::query()->find($donnees['type_document_id'] ?? null);
        $delai = $type?->delai_renouvellement_jours;

        if ($type === null || $delai === null) {
            return;
        }

        $date = Carbon::parse($donnees['date_production'] ?? now());

        $existant = Document::query()
            ->where('matricule', $employe->matricule)
            ->where('type_document_id', $type->getKey())
            ->whereBetween('date_production', [$date->copy()->subDays($delai)->toDateString(), $date->copy()->addDays($delai)->toDateString()])
            ->whereHas('workflow', fn ($q) => $q->whereNotIn('statut', [WorkflowStatut::Rejete->value, WorkflowStatut::Expire->value]))
            ->orderByDesc('date_production')
            ->first();

        if ($existant !== null) {
            throw new RegleMetierException(sprintf(
                'Un document « %s » a déjà été établi pour cet agent le %s ; le renouvellement est possible après %d jours.',
                $type->name,
                $existant->date_production->format('d/m/Y'),
                $delai,
            ));
        }
    }

    /**
     * Champs calculés par le serveur (jamais assignables en masse) : posés ici explicitement.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function remplir(Model $enregistrement, array $donnees): Model
    {
        $calcules = array_intersect_key($donnees, ['note' => true]);

        return $enregistrement->fill(array_diff_key($donnees, $calcules))->forceFill($calcules);
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function noterEvaluation(array $donnees): array
    {
        $modele = ModelEvaluation::query()->find($donnees['model_evaluation_id'] ?? null);
        $resultat = $this->evaluations->noter($modele, $donnees['notes_criteres'] ?? null);

        // La note finale saisie est toujours écartée au profit du calcul.
        return ['notes_criteres' => $resultat['notes_criteres'], 'note' => $resultat['note']] + $donnees;
    }

    /** Une promotion ou mutation ne peut prendre effet avant le début de l'affectation courante de l'agent. */
    private function verifierDateEffet(Employe $employe, mixed $dateEffet): void
    {
        $debut = $employe->affectationCourante()->first()?->date_debut;

        if ($debut !== null && $dateEffet !== null && Carbon::parse($dateEffet)->lt($debut)) {
            throw new RegleMetierException(sprintf(
                'La date d\'effet ne peut pas précéder le début de l\'affectation actuelle de l\'agent (%s).',
                $debut->format('d/m/Y'),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function calculerJoursConge(array $donnees, string $matricule, ?int $ignorerCongeId = null): float
    {
        $type = TypeConge::query()->findOrFail($donnees['type_conge_id'] ?? null);

        return $this->conges->preparer(
            $matricule,
            $type,
            Carbon::parse($donnees['date_debut']),
            Carbon::parse($donnees['date_fin']),
            $ignorerCongeId,
            (bool) ($donnees['demi_debut'] ?? false),
            (bool) ($donnees['demi_fin'] ?? false),
        );
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
                $donnees['nbre_jours'] = $this->calculerJoursConge($donnees, $employe->matricule);
                break;
            case ModuleRh::Evaluation:
                $donnees['evaluateur_id'] ??= $acteur->getKey();
                $donnees = $this->noterEvaluation($donnees);
                break;
            case ModuleRh::Promotion:
                $this->verifierDateEffet($employe, $donnees['date_effet'] ?? null);
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
        $this->verifierDateEffet($employe, $donnees['date_effet'] ?? null);
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
