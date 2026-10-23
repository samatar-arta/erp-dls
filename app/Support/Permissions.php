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
    public const RH_VIEW = 'rh.view';
    public const RH_CREATE = 'rh.create';
    public const RH_UPDATE = 'rh.update';
    public const SANCTIONS_VIEW = 'sanctions.view';
    public const SANCTIONS_MANAGE = 'sanctions.manage';
    public const SALAIRES_VIEW = 'salaires.view';
    public const WORKFLOW_SOUMETTRE = 'workflow.soumettre';
    public const WORKFLOW_VALIDER = 'workflow.valider';
    public const WORKFLOW_APPROUVER = 'workflow.approuver';

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
            self::RH_VIEW,
            self::RH_CREATE,
            self::RH_UPDATE,
            self::SANCTIONS_VIEW,
            self::SANCTIONS_MANAGE,
            self::SALAIRES_VIEW,
            self::WORKFLOW_SOUMETTRE,
            self::WORKFLOW_VALIDER,
            self::WORKFLOW_APPROUVER,
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
            RoleName::RhSenior => [
                ...$lecture,
                self::EMPLOYES_CREATE,
                self::EMPLOYES_UPDATE,
                self::FICHIERS_MANAGE,
                self::RH_VIEW,
                self::RH_CREATE,
                self::RH_UPDATE,
                self::SANCTIONS_VIEW,
                self::SANCTIONS_MANAGE,
                self::WORKFLOW_SOUMETTRE,
                self::WORKFLOW_VALIDER,
            ],
            RoleName::RhOfficer => [
                ...$lecture,
                self::EMPLOYES_CREATE,
                self::EMPLOYES_UPDATE,
                self::FICHIERS_MANAGE,
                self::RH_VIEW,
                self::RH_CREATE,
                self::RH_UPDATE,
                self::WORKFLOW_SOUMETTRE,
            ],
            RoleName::RhJunior => [
                ...$lecture,
                self::EMPLOYES_CREATE,
                self::EMPLOYES_UPDATE,
                self::RH_VIEW,
                self::RH_CREATE,
                self::RH_UPDATE,
                self::WORKFLOW_SOUMETTRE,
            ],
            default => [],
        };
    }
}
