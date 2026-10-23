<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Données de référence uniquement : aucun compte avec mot de passe codé en dur.
 * Premier administrateur : php artisan dls:creer-admin. Données fictives : DemoSeeder (local).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            OrganisationSeeder::class,
            ModulesRhSeeder::class,
        ]);
    }
}
