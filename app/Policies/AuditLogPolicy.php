<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Permissions;

/**
 * Consultation seule, réservée à la permission audit.view (ADMIN et RH_MANAGER). Aucune écriture par l'interface.
 */
class AuditLogPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::AUDIT_VIEW);
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->can(Permissions::AUDIT_VIEW);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
