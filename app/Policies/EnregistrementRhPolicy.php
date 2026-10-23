<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkflowStatut;
use App\Models\Employe;
use App\Models\User;
use App\Services\PerimetreService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Droits communs aux enregistrements des modules RH (documents, congés, évaluations, promotions,
 * mutations). L'accès suit toujours le périmètre de l'employé concerné. Les actions du workflow
 * (soumettre, valider, approuver) sont contrôlées par WorkflowService.
 */
class EnregistrementRhPolicy
{
    protected string $permissionLecture = Permissions::RH_VIEW;

    protected string $permissionEcriture = Permissions::RH_UPDATE;

    protected string $permissionCreation = Permissions::RH_CREATE;

    public function __construct(protected readonly PerimetreService $perimetre)
    {
    }

    public function before(User $user): ?bool
    {
        // Un compte désactivé n'a plus aucun droit, quel que soit son rôle.
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->permissionLecture);
    }

    public function view(User $user, Model $enregistrement): bool
    {
        return $user->can($this->permissionLecture) && $this->dansLePerimetre($user, $enregistrement);
    }

    public function create(User $user): bool
    {
        return $user->can($this->permissionCreation);
    }

    /** Modifiable uniquement tant que c'est un brouillon (ensuite, seul le workflow agit). */
    public function update(User $user, Model $enregistrement): bool
    {
        return $user->can($this->permissionEcriture)
            && $this->dansLePerimetre($user, $enregistrement)
            && $this->estBrouillon($enregistrement);
    }

    public function delete(User $user, Model $enregistrement): bool
    {
        return $this->update($user, $enregistrement) && $enregistrement->getAttribute('reference') === null;
    }

    protected function dansLePerimetre(User $user, Model $enregistrement): bool
    {
        $employe = $enregistrement->employe;

        return $employe instanceof Employe && $this->perimetre->peutAccederEmploye($user, $employe);
    }

    protected function estBrouillon(Model $enregistrement): bool
    {
        return $enregistrement->workflow?->statut === WorkflowStatut::Brouillon;
    }
}
