<?php

declare(strict_types=1);

namespace App\Enums;

enum ModeCalcul: string
{
    case Calendaire = 'calendaire';
    case Ouvre = 'ouvre';

    public function label(): string
    {
        return match ($this) {
            self::Calendaire => 'Jours calendaires',
            self::Ouvre => 'Jours ouvrés',
        };
    }
}
