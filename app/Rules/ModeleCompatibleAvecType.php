<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\ModuleRh;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle de validation réutilisable par les six modules : le modèle doit exister, être actif
 * et appartenir au type donné.
 *
 *     'model_document_id' => [new ModeleCompatibleAvecType(ModuleRh::Document, $typeId)]
 */
class ModeleCompatibleAvecType implements ValidationRule
{
    public function __construct(
        private readonly ModuleRh $module,
        private readonly int|string|null $typeId,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $modele = $this->module->modeleClass()::query()->find($value);

        if ($modele === null) {
            $fail('Le modèle sélectionné est introuvable.');

            return;
        }

        if ((int) $modele->getAttribute($this->module->cleType()) !== (int) $this->typeId) {
            $fail('Le modèle sélectionné ne correspond pas au type choisi.');

            return;
        }

        if (! $modele->getAttribute('is_active')) {
            $fail('Le modèle sélectionné est inactif.');
        }
    }
}
