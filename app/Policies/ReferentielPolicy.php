<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Permissions;

/**
 * Types de contrats, grades et autres référentiels administrables.
 */
class ReferentielPolicy extends OrganisationPolicy
{
    protected string $permissionLecture = Permissions::REFERENTIELS_VIEW;

    protected string $permissionEcriture = Permissions::REFERENTIELS_MANAGE;
}
