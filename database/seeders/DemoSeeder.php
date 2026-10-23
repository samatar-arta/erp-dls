<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\SiteAffectation;
use App\Models\SiteDepartement;
use App\Models\Societe;
use App\Models\TypeContrat;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Données fictives pour le développement local uniquement.
 * Mot de passe des comptes de démonstration : variable DEMO_PASSWORD (voir .env.example).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder est réservé à l\'environnement local.');
        }

        $this->call([RolesAndPermissionsSeeder::class, OrganisationSeeder::class, ModulesRhSeeder::class]);

        $structure = [
            'Siège DLS' => ['DLS' => ['Direction Générale', 'Ressources humaines', 'Finance', 'Opérations']],
            'Site DMP' => ['DMP' => ['DSI', 'OPS', 'TECHNIQUE']],
            'Site SGTD' => ['SGTD' => ['Exploitation', 'Maintenance']],
            'Site IDIPO' => ['IDIPO' => ['Logistique']],
        ];

        $couples = [];
        foreach ($structure as $nomSite => $societes) {
            $site = SiteAffectation::query()->firstOrCreate(['name' => $nomSite]);
            foreach ($societes as $code => $departements) {
                $societe = Societe::query()->where('code', $code)->firstOrFail();
                foreach ($departements as $nom) {
                    $departement = Departement::query()->firstOrCreate(
                        ['societe_id' => $societe->id, 'name' => $nom],
                        ['abrege' => mb_strtoupper(mb_substr($nom, 0, 4))],
                    );
                    $couples[$code.'/'.$nom] = SiteDepartement::query()->firstOrCreate([
                        'site_id' => $site->id,
                        'societe_id' => $societe->id,
                        'departement_id' => $departement->id,
                    ]);
                }
            }
        }

        $contrat = TypeContrat::query()->first();
        foreach ($couples as $couple) {
            if (Employe::query()->where('site_id', $couple->site_id)->where('departement_id', $couple->departement_id)->exists()) {
                continue;
            }
            Employe::factory()->count(3)->pourSiteDepartement($couple)->create(['type_contrat_id' => $contrat?->id]);
        }

        $motDePasse = (string) env('DEMO_PASSWORD', 'Demo!Passw0rd2026');
        $perimetreDmp = [$couples['DMP/DSI']->id, $couples['DMP/OPS']->id, $couples['DMP/TECHNIQUE']->id];

        foreach (RoleName::cases() as $role) {
            $utilisateur = User::query()->firstOrCreate(
                ['email' => strtolower($role->value).'@demo.dls.test'],
                ['name' => 'Démo '.$role->label(), 'password' => $motDePasse, 'is_active' => true],
            );
            $utilisateur->syncRoles([$role->value]);
            $utilisateur->siteDepartements()->sync($role->estGlobal() ? [] : $perimetreDmp);
        }
    }
}
