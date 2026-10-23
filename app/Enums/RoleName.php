<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'ADMIN';
    case RhManager = 'RH_MANAGER';
    case RhSenior = 'RH_SENIOR';
    case RhOfficer = 'RH_OFFICER';
    case RhJunior = 'RH_JUNIOR';

    /**
     * Rôles à accès global (tous dossiers, tous périmètres), aux droits strictement identiques.
     *
     * @return list<self>
     */
    public static function globaux(): array
    {
        return [self::Admin, self::RhManager];
    }

    /**
     * @return list<string>
     */
    public static function globauxValeurs(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::globaux());
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    public function estGlobal(): bool
    {
        return in_array($this, self::globaux(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::RhManager => 'Responsable RH',
            self::RhSenior => 'RH senior',
            self::RhOfficer => 'Chargé RH',
            self::RhJunior => 'RH junior',
        };
    }
}
