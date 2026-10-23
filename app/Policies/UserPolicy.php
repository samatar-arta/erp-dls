<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::USERS_MANAGE);
    }

    public function view(User $user, User $cible): bool
    {
        return $user->can(Permissions::USERS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::USERS_MANAGE);
    }

    public function update(User $user, User $cible): bool
    {
        return $user->can(Permissions::USERS_MANAGE);
    }

    /**
     * Rôles et affectations : jamais les siens (voir aussi UserManagementService).
     */
    public function updateRoles(User $user, User $cible): bool
    {
        return $user->can(Permissions::USERS_MANAGE) && ! $user->is($cible);
    }

    public function updateAffectations(User $user, User $cible): bool
    {
        return $this->updateRoles($user, $cible);
    }

    public function delete(User $user, User $cible): bool
    {
        return $user->can(Permissions::USERS_MANAGE) && ! $user->is($cible);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permissions::USERS_MANAGE);
    }
}
