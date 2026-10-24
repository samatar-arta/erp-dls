<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SoldeConge;
use App\Models\User;
use App\Services\PerimetreService;
use App\Support\Permissions;

/**
 * Lecture dans le périmètre de l'agent ; ajustement des droits acquis / report réservé à la gestion des
 * référentiels (ADMIN et RH_MANAGER). Création et suppression sont réservées au service des congés.
 */
class SoldeCongePolicy
{
    public function __construct(private readonly PerimetreService $perimetre)
    {
    }

    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::RH_VIEW);
    }

    public function view(User $user, SoldeConge $solde): bool
    {
        $employe = $solde->employe;

        return $user->can(Permissions::RH_VIEW) && $employe !== null && $this->perimetre->peutAccederEmploye($user, $employe);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SoldeConge $solde): bool
    {
        return $user->can(Permissions::REFERENTIELS_MANAGE) && $this->view($user, $solde);
    }

    public function delete(User $user, SoldeConge $solde): bool
    {
        return false;
    }
}
