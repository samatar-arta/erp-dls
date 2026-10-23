<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employe;
use App\Models\User;
use App\Services\PerimetreService;
use App\Support\Permissions;

class EmployePolicy
{
    public function __construct(private readonly PerimetreService $perimetre)
    {
    }

    public function before(User $user): ?bool
    {
        // Un compte désactivé n'a plus aucun droit, quel que soit son rôle.
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::EMPLOYES_VIEW);
    }

    public function view(User $user, Employe $employe): bool
    {
        return $user->can(Permissions::EMPLOYES_VIEW) && $this->perimetre->peutAccederEmploye($user, $employe);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::EMPLOYES_CREATE);
    }

    public function update(User $user, Employe $employe): bool
    {
        return $user->can(Permissions::EMPLOYES_UPDATE) && $this->perimetre->peutAccederEmploye($user, $employe);
    }

    public function delete(User $user, Employe $employe): bool
    {
        return $user->can(Permissions::EMPLOYES_DELETE) && $this->perimetre->peutAccederEmploye($user, $employe);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permissions::EMPLOYES_DELETE);
    }

    public function viewFichiers(User $user, Employe $employe): bool
    {
        return $user->can(Permissions::FICHIERS_VIEW) && $this->perimetre->peutAccederEmploye($user, $employe);
    }

    public function manageFichiers(User $user, Employe $employe): bool
    {
        return $user->can(Permissions::FICHIERS_MANAGE) && $this->perimetre->peutAccederEmploye($user, $employe);
    }
}
