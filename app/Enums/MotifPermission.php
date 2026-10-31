<?php

declare(strict_types=1);

namespace App\Enums;

/** Permissions exceptionnelles pour événements familiaux (Code du travail) et leur barème en jours ouvrés. */
enum MotifPermission: string
{
    case MariageTravailleur = 'MARIAGE_TRAVAILLEUR';
    case MariageEnfant = 'MARIAGE_ENFANT';
    case DecesProche = 'DECES_CONJOINT_ENFANT_PARENT';
    case DecesFratrie = 'DECES_FRERE_SOEUR';
    case DecesBeauParent = 'DECES_BEAU_PARENT';
    case Naissance = 'NAISSANCE_ENFANT';

    /** Crédit maximal par employé et par année civile (date de début de la permission). */
    public const PLAFOND_ANNUEL = 11.0;

    public function jours(): float
    {
        return match ($this) {
            self::MariageTravailleur, self::DecesProche, self::Naissance => 3.0,
            self::MariageEnfant, self::DecesFratrie, self::DecesBeauParent => 1.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MariageTravailleur => 'Mariage du travailleur',
            self::MariageEnfant => 'Mariage d\'un enfant',
            self::DecesProche => 'Décès du conjoint, d\'un enfant, du père ou de la mère',
            self::DecesFratrie => 'Décès du frère ou de la sœur',
            self::DecesBeauParent => 'Décès du beau-père ou de la belle-mère',
            self::Naissance => 'Naissance d\'un enfant (congé parental)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $cas) {
            $options[$cas->value] = sprintf('%s (%d j)', $cas->label(), $cas->jours());
        }

        return $options;
    }
}
