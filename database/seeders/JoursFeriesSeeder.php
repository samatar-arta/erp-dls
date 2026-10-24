<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\JourFerie;
use Illuminate\Database\Seeder;

/**
 * Fêtes à date fixe uniquement. Les fêtes religieuses (dates variables) se saisissent chaque année
 * dans l'écran « Jours fériés ».
 */
class JoursFeriesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['2026-01-01', 'Nouvel An'],
            ['2026-05-01', 'Fête du Travail'],
            ['2026-06-27', 'Fête de l\'Indépendance'],
        ] as [$date, $libelle]) {
            JourFerie::query()->firstOrCreate(['date_jour' => $date], ['libelle' => $libelle, 'recurrent' => true]);
        }
    }
}
