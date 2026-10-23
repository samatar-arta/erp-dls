<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\ModuleRh;
use App\Models\Employe;
use App\Models\NiveauSanction;
use App\Models\User;
use Database\Seeders\ModulesRhSeeder;
use Illuminate\Database\Eloquent\Model;

trait CreeModulesRh
{
    protected function seederModules(): void
    {
        $this->seed(ModulesRhSeeder::class);
    }

    /**
     * Deux types distincts du module, chacun avec son modèle par défaut.
     *
     * @return array{0: Model, 1: Model, 2: Model, 3: Model}  [typeA, modeleA, typeB, modeleB]
     */
    protected function deuxTypes(ModuleRh $module): array
    {
        $types = $module->typeClass()::query()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $types, 'Le module doit avoir au moins deux types seedés.');

        $modeleA = $module->modeleClass()::query()->where($module->cleType(), $types[0]->id)->firstOrFail();
        $modeleB = $module->modeleClass()::query()->where($module->cleType(), $types[1]->id)->firstOrFail();

        return [$types[0], $modeleA, $types[1], $modeleB];
    }

    /**
     * Attributs minimaux valides d'un enregistrement du module.
     *
     * @return array<string, mixed>
     */
    protected function attributsEnregistrement(ModuleRh $module, Employe $employe, int $typeId, ?int $modeleId): array
    {
        $communs = [
            'matricule' => $employe->matricule,
            $module->cleType() => $typeId,
            $module->cleModele() => $modeleId,
        ];

        return $communs + match ($module) {
            ModuleRh::Document => ['date_production' => '2026-03-01'],
            ModuleRh::Conge => ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-06', 'nbre_jours' => 5],
            ModuleRh::Sanction => [
                'niveau_sanction_id' => NiveauSanction::query()->firstOrCreate(['code' => 'AVERTISSEMENT'], ['name' => 'Avertissement'])->id,
                'date_sanction' => '2026-03-02',
                'description' => 'Retard répété.',
            ],
            ModuleRh::Evaluation => [
                'evaluateur_id' => User::factory()->create()->id,
                'date_evaluation' => '2026-03-02',
            ],
            ModuleRh::Promotion => ['nouvelle_fonction' => 'Chef d\'équipe', 'date_effet' => '2026-04-01'],
            ModuleRh::Mutation => [
                'ancien_site_id' => $employe->site_id,
                'ancien_departement_id' => $employe->departement_id,
                'ancienne_societe_id' => $employe->societe_id,
                'date_effet' => '2026-04-01',
            ],
        };
    }

    protected function creerEnregistrement(ModuleRh $module, Employe $employe, Model $type, ?Model $modele): Model
    {
        return $module->enregistrementClass()::query()->create(
            $this->attributsEnregistrement($module, $employe, $type->getKey(), $modele?->getKey()),
        );
    }

    /**
     * @return array<string, array{0: ModuleRh}>
     */
    public static function modules(): array
    {
        $cas = [];
        foreach (ModuleRh::cases() as $module) {
            $cas[$module->value] = [$module];
        }

        return $cas;
    }
}
