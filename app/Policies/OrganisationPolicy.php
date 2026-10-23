<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Sociétés, sites, départements, site-départements : lecture pour tout le personnel RH, écriture réservée.
 */
class OrganisationPolicy
{
    protected string $permissionLecture = Permissions::ORGANISATION_VIEW;

    protected string $permissionEcriture = Permissions::ORGANISATION_MANAGE;

    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->permissionLecture);
    }

    public function view(User $user, Model $modele): bool
    {
        return $user->can($this->permissionLecture);
    }

    public function create(User $user): bool
    {
        return $user->can($this->permissionEcriture);
    }

    public function update(User $user, Model $modele): bool
    {
        return $user->can($this->permissionEcriture);
    }

    public function delete(User $user, Model $modele): bool
    {
        return $user->can($this->permissionEcriture);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->permissionEcriture);
    }
}
