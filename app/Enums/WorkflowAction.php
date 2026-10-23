<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Permissions;

/**
 * Matrice des transitions du workflow : statuts de départ, statut d'arrivée, permission requise.
 * Toute transition absente de cette matrice est refusée par WorkflowService.
 */
enum WorkflowAction: string
{
    case Soumettre = 'SOUMETTRE';
    case PrendreEnCharge = 'PRENDRE_EN_CHARGE';
    case Valider = 'VALIDER';
    case Transmettre = 'TRANSMETTRE';
    case Approuver = 'APPROUVER';
    case Rejeter = 'REJETER';
    case Rouvrir = 'ROUVRIR';
    case Expirer = 'EXPIRER';

    public function label(): string
    {
        return match ($this) {
            self::Soumettre => 'Soumettre',
            self::PrendreEnCharge => 'Prendre en charge',
            self::Valider => 'Valider',
            self::Transmettre => 'Transmettre pour approbation',
            self::Approuver => 'Approuver',
            self::Rejeter => 'Rejeter',
            self::Rouvrir => 'Rouvrir',
            self::Expirer => 'Expirer',
        };
    }

    /** @return list<WorkflowStatut> */
    public function depuis(): array
    {
        return match ($this) {
            self::Soumettre => [WorkflowStatut::Brouillon],
            self::PrendreEnCharge => [WorkflowStatut::DocumentSoumis],
            self::Valider => [WorkflowStatut::EnAttenteValidation],
            self::Transmettre => [WorkflowStatut::Valide],
            self::Approuver => [WorkflowStatut::EnAttenteApprobation],
            self::Rejeter => [WorkflowStatut::DocumentSoumis, WorkflowStatut::EnAttenteValidation, WorkflowStatut::Valide, WorkflowStatut::EnAttenteApprobation],
            self::Rouvrir => [WorkflowStatut::Rejete, WorkflowStatut::Expire],
            self::Expirer => [WorkflowStatut::DocumentSoumis, WorkflowStatut::EnAttenteValidation, WorkflowStatut::EnAttenteApprobation],
        };
    }

    public function vers(): WorkflowStatut
    {
        return match ($this) {
            self::Soumettre => WorkflowStatut::DocumentSoumis,
            self::PrendreEnCharge => WorkflowStatut::EnAttenteValidation,
            self::Valider => WorkflowStatut::Valide,
            self::Transmettre => WorkflowStatut::EnAttenteApprobation,
            self::Approuver => WorkflowStatut::Approuve,
            self::Rejeter => WorkflowStatut::Rejete,
            self::Rouvrir => WorkflowStatut::Brouillon,
            self::Expirer => WorkflowStatut::Expire,
        };
    }

    /** Permission requise, selon le statut courant (le rejet d'une approbation exige le droit d'approuver). */
    public function permission(WorkflowStatut $courant): ?string
    {
        return match ($this) {
            self::Soumettre, self::Rouvrir => Permissions::WORKFLOW_SOUMETTRE,
            self::PrendreEnCharge, self::Valider, self::Transmettre => Permissions::WORKFLOW_VALIDER,
            self::Approuver => Permissions::WORKFLOW_APPROUVER,
            self::Rejeter => $courant === WorkflowStatut::EnAttenteApprobation
                ? Permissions::WORKFLOW_APPROUVER
                : Permissions::WORKFLOW_VALIDER,
            self::Expirer => null,
        };
    }

    /** Actions où l'auteur / le soumetteur ne peut pas être l'acteur (séparation des responsabilités). */
    public function appliqueSeparation(): bool
    {
        return in_array($this, [self::PrendreEnCharge, self::Valider, self::Transmettre, self::Approuver, self::Rejeter], true);
    }

    public function exigeCommentaire(): bool
    {
        return $this === self::Rejeter;
    }

    /** Action réservée au système (planificateur), jamais à un utilisateur. */
    public function estSysteme(): bool
    {
        return $this === self::Expirer;
    }
}
