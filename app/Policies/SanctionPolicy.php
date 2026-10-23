<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Permissions;

/**
 * Accès renforcé : les sanctions sont réservées aux rôles disposant de sanctions.view / sanctions.manage
 * (ADMIN, RH_MANAGER, RH_SENIOR). Un officier ou un junior ne les voit pas, même dans son périmètre.
 */
class SanctionPolicy extends EnregistrementRhPolicy
{
    protected string $permissionLecture = Permissions::SANCTIONS_VIEW;

    protected string $permissionEcriture = Permissions::SANCTIONS_MANAGE;

    protected string $permissionCreation = Permissions::SANCTIONS_MANAGE;
}
