<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\ModuleRh;
use App\Exceptions\RegleMetierException;
use App\Services\TemplateRendererService;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comportement commun des six modèles (templates HTML) : nettoyage du contenu, un seul modèle
 * par défaut actif par type, version incrémentée quand un modèle déjà utilisé est modifié.
 *
 * Le modèle hôte définit TYPE_FK et usages().
 */
trait EstUnModele
{
    abstract protected function usages(): HasMany;

    protected static function bootEstUnModele(): void
    {
        static::saving(static function (Model $modele): void {
            if ($modele->isDirty('contenu')) {
                // Seules les variables de la liste blanche du module sont acceptées.
                $inconnues = app(TemplateRendererService::class)->variablesInconnues(ModuleRh::pourModele($modele), (string) $modele->contenu);
                if ($inconnues !== []) {
                    throw new RegleMetierException('Variable(s) non autorisée(s) dans le modèle : '.implode(', ', $inconnues).'.');
                }

                $modele->contenu = HtmlSanitizer::nettoyer((string) $modele->contenu);
            }

            // is_active absent à la création = valeur par défaut de la base (actif).
            if ($modele->is_active === false) {
                $modele->is_default = false;
            }

            // Un seul modèle par défaut actif par type : le précédent perd son statut.
            if ($modele->is_default) {
                static::query()
                    ->where(static::TYPE_FK, $modele->{static::TYPE_FK})
                    ->when($modele->exists, fn (Builder $q) => $q->whereKeyNot($modele->getKey()))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(static function (Model $modele): void {
            $champsDeFond = array_filter(['titre', 'contenu', 'criteres'], static fn (string $c): bool => $modele->isDirty($c));

            if ($champsDeFond !== [] && ! $modele->isDirty('version') && $modele->estUtilise()) {
                $modele->version = (int) $modele->getOriginal('version') + 1;
            }
        });
    }

    public function estUtilise(): bool
    {
        return $this->usages()->exists();
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePourType(Builder $query, int $typeId): Builder
    {
        return $query->where(static::TYPE_FK, $typeId);
    }
}
