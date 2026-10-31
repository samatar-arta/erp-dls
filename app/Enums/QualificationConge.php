<?php

declare(strict_types=1);

namespace App\Enums;

/** Qualification d'un congé annuel selon sa durée en jours calendaires. */
enum QualificationConge: string
{
    case TitreConge = 'TITRE_CONGE';
    case AutorisationAbsence = 'AUTORISATION_ABSENCE';

    /** À partir de ce nombre de jours calendaires, la demande est un titre de congé. */
    public const SEUIL_TITRE_CONGE = 30.0;

    /** Durée minimale (en jours calendaires) d'une autorisation d'absence. */
    public const SEUIL_MINIMAL_AUTORISATION = 5.0;

    public static function pourDuree(float $joursCalendaires): self
    {
        return $joursCalendaires >= self::SEUIL_TITRE_CONGE ? self::TitreConge : self::AutorisationAbsence;
    }

    public function label(): string
    {
        return match ($this) {
            self::TitreConge => 'Titre de congé',
            self::AutorisationAbsence => 'Autorisation d\'absence',
        };
    }
}
