<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ModuleRh;
use App\Services\ReferenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Préfixe et format de référence par module. Idempotent : ne touche jamais une configuration existante.
 */
class ReferencesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ModuleRh::cases() as $module) {
            DB::table('reference_modules')->insertOrIgnore([
                'module' => $module->value,
                'prefixe' => $module->prefixeParDefaut(),
                'format' => ReferenceService::FORMAT_PAR_DEFAUT,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
