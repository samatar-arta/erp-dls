<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkflowStatut: string
{
    case Brouillon = 'BROUILLON';
    case DocumentSoumis = 'DOCUMENT_SOUMIS';
    case EnAttenteValidation = 'EN_ATTENTE_VALIDATION';
    case Valide = 'VALIDE';
    case EnAttenteApprobation = 'EN_ATTENTE_APPROBATION';
    case Approuve = 'APPROUVE';
    case Rejete = 'REJETE';
    case Expire = 'EXPIRE';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::DocumentSoumis => 'Soumis',
            self::EnAttenteValidation => 'En attente de validation',
            self::Valide => 'Validé',
            self::EnAttenteApprobation => 'En attente d\'approbation',
            self::Approuve => 'Approuvé',
            self::Rejete => 'Rejeté',
            self::Expire => 'Expiré',
        };
    }

    /** Statuts qui n'évolueront plus sans réouverture explicite. */
    public function estFinal(): bool
    {
        return in_array($this, [self::Approuve, self::Rejete, self::Expire], true);
    }

    /** Statuts soumis à un délai de traitement (échéance). */
    public function estEnAttente(): bool
    {
        return in_array($this, [self::DocumentSoumis, self::EnAttenteValidation, self::EnAttenteApprobation], true);
    }
}
