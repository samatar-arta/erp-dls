<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleName;

/**
 * Catalogue des permissions et leur attribution par rôle.
 *
 * ADMIN et RH_MANAGER reçoivent exactement le même jeu (voir pourRole()).
 */
final class Permissions
{
    public const ORGANISATION_VIEW = 'organisation.view';
    public const ORGANISATION_MANAGE = 'organisation.manage';
    public const REFERENTIELS_VIEW = 'referentiels.view';
    public const REFERENTIELS_MANAGE = 'referentiels.manage';
    public const USERS_MANAGE = 'users.manage';
    public const EMPLOYES_VIEW = 'employes.view';
    public const EMPLOYES_CREATE = 'employes.create';
    public const EMPLOYES_UPDATE = 'employes.update';
    public const EMPLOYES_DELETE = 'employes.delete';
    public const FICHIERS_VIEW = 'employes.fichiers.view';
    public const FICHIERS_MANAGE = 'employes.fichiers.manage';
    public const AUDIT_VIEW = 'audit.view';
    public const PARAMETRES_MANAGE = 'parametres.manage';

    /**
     * @return list<string>
     */
    public static function toutes(): array
    {
        return [
            self::ORGANISATION_VIEW,
            self::ORGANISATION_MANAGE,
            self::REFERENTIELS_VIEW,
            self::REFERENTIELS_MANAGE,
            self::USERS_MANAGE,
            self::EMPLOYES_VIEW,
            self::EMPLOYES_CREATE,
            self::EMPLOYES_UPDATE,
            self::EMPLOYES_DELETE,
            self::FICHIERS_VIEW,
            self::FICHIERS_MANAGE,
            self::AUDIT_VIEW,
            self::PARAMETRES_MANAGE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function pourRole(RoleName $role): array
    {
        if ($role->estGlobal()) {
            return self::toutes();
        }

        $lecture = [self::ORGANISATION_VIEW, self::REFERENTIELS_VIEW, self::EMPLOYES_VIEW, self::FICHIERS_VIEW];

        return match ($role) {
            RoleName::RhSenior, RoleName::RhOfficer => [
                ...$lecture,
                self::EMPLOYES_CREATE,
                self::EMPLOYES_UPDATE,
                self::FICHIERS_MANAGE,
            ],
            RoleName::RhJunior => [
                ...$lecture,
                self::EMPLOYES_CREATE,
                self::EMPLOYES_UPDATE,
            ],
            default => [],
        };
    }
}
