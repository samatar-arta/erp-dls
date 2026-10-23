<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Conge;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\ModelConge;
use App\Models\ModelDocument;
use App\Models\ModelEvaluation;
use App\Models\ModelMutation;
use App\Models\ModelPromotion;
use App\Models\ModelSanction;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\Sanction;
use App\Models\TypeConge;
use App\Models\TypeDocument;
use App\Models\TypeEvaluation;
use App\Models\TypeMutation;
use App\Models\TypePromotion;
use App\Models\TypeSanction;

/**
 * Les six modules métier RH. Chacun possède ses types, ses modèles, ses enregistrements
 * et (Phase 3) son propre compteur de références annuel.
 */
enum ModuleRh: string
{
    case Document = 'DOCUMENT';
    case Conge = 'CONGE';
    case Sanction = 'SANCTION';
    case Evaluation = 'EVALUATION';
    case Promotion = 'PROMOTION';
    case Mutation = 'MUTATION';

    /** Alias du morph map (workflows polymorphes, Phase 3). */
    public function alias(): string
    {
        return strtolower($this->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Document => 'Documents',
            self::Conge => 'Congés',
            self::Sanction => 'Sanctions',
            self::Evaluation => 'Évaluations',
            self::Promotion => 'Promotions',
            self::Mutation => 'Mutations',
        };
    }

    /** Préfixe de référence par défaut (modifiable ensuite par ADMIN/RH_MANAGER). */
    public function prefixeParDefaut(): string
    {
        return match ($this) {
            self::Document => 'DA_DARH/DLS',
            self::Sanction => 'DS_DARH/DLS',
            self::Evaluation => 'EV_DARH/DLS',
            self::Promotion => 'PR_DARH/DLS',
            self::Mutation => 'MU_DARH/DLS',
            self::Conge => 'CG_DARH/DLS',
        };
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function typeClass(): string
    {
        return match ($this) {
            self::Document => TypeDocument::class,
            self::Conge => TypeConge::class,
            self::Sanction => TypeSanction::class,
            self::Evaluation => TypeEvaluation::class,
            self::Promotion => TypePromotion::class,
            self::Mutation => TypeMutation::class,
        };
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function modeleClass(): string
    {
        return match ($this) {
            self::Document => ModelDocument::class,
            self::Conge => ModelConge::class,
            self::Sanction => ModelSanction::class,
            self::Evaluation => ModelEvaluation::class,
            self::Promotion => ModelPromotion::class,
            self::Mutation => ModelMutation::class,
        };
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function enregistrementClass(): string
    {
        return match ($this) {
            self::Document => Document::class,
            self::Conge => Conge::class,
            self::Sanction => Sanction::class,
            self::Evaluation => Evaluation::class,
            self::Promotion => Promotion::class,
            self::Mutation => Mutation::class,
        };
    }

    /** Colonne « type » des modèles et enregistrements (ex. type_document_id). */
    public function cleType(): string
    {
        return $this->enregistrementClass()::TYPE_FK;
    }

    /** Colonne « modèle » des enregistrements (ex. model_document_id). */
    public function cleModele(): string
    {
        return $this->enregistrementClass()::MODELE_FK;
    }
}
