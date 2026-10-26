<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\JourFerie;
use Illuminate\Database\Seeder;

/**
 * Fêtes fixes (récurrentes) et fêtes musulmanes mobiles de 2026 (non récurrentes : à ressaisir chaque année
 * dans l'écran « Jours fériés », qui reste la table de configuration). Un férié tombant un vendredi ou un samedi
 * n'est pas reporté au dimanche, sauf décret : il suffit alors d'ajouter la date du report dans cet écran.
 */
class JoursFeriesSeeder extends Seeder
{
    public function run(): void
    {
        $jours = [
            ['2026-01-01', 'Nouvel An', true],
            ['2026-05-01', 'Fête du Travail', true],
            ['2026-06-27', 'Fête de l\'Indépendance', true],
            ['2026-06-28', 'Fête de l\'Indépendance (2e jour)', true],
            ['2026-03-20', 'Aïd al-Fitr', false],
            ['2026-03-21', 'Aïd al-Fitr (2e jour)', false],
            ['2026-05-26', 'Journée d\'Arafat', false],
            ['2026-05-27', 'Aïd al-Adha', false],
            ['2026-05-28', 'Aïd al-Adha (2e jour)', false],
            ['2026-06-16', 'Nouvel An musulman', false],
            ['2026-08-25', 'Mouloud', false],
        ];

        foreach ($jours as [$date, $libelle, $recurrent]) {
            JourFerie::query()->firstOrCreate(['date_jour' => $date], ['libelle' => $libelle, 'recurrent' => $recurrent]);
        }
    }
}
