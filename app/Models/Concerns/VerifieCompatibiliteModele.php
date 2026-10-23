<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\RegleMetierException;
use Illuminate\Database\Eloquent\Model;

/**
 * Garde-fou serveur commun aux six enregistrements métier : le modèle doit appartenir au type
 * choisi et être actif. S'applique même si un identifiant incompatible est forcé dans la requête.
 *
 * L'hôte définit TYPE_FK, MODELE_FK, TYPE_CLASS et MODELE_CLASS.
 */
trait VerifieCompatibiliteModele
{
    protected static function bootVerifieCompatibiliteModele(): void
    {
        static::saving(static function (Model $enregistrement): void {
            $typeFk = static::TYPE_FK;
            $modeleFk = static::MODELE_FK;

            if ($enregistrement->exists && ! $enregistrement->isDirty([$typeFk, $modeleFk])) {
                return;
            }

            $modeleId = $enregistrement->{$modeleFk};

            if ($modeleId === null) {
                $type = (static::TYPE_CLASS)::query()->find($enregistrement->{$typeFk});
                if ($type === null || $type->getAttribute('requires_model') !== false) {
                    throw new RegleMetierException('Un modèle est requis pour ce type.');
                }

                return;
            }

            $modele = (static::MODELE_CLASS)::query()->find($modeleId);

            if ($modele === null || (int) $modele->{$typeFk} !== (int) $enregistrement->{$typeFk}) {
                throw new RegleMetierException('Le modèle sélectionné ne correspond pas au type choisi.');
            }

            if (! $modele->is_active) {
                throw new RegleMetierException('Le modèle sélectionné est inactif.');
            }
        });
    }
}
