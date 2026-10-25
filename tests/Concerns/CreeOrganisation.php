<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\RoleName;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\SiteAffectation;
use App\Models\SiteDepartement;
use App\Models\Societe;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait CreeOrganisation
{
    protected Societe $societeA;

    protected Societe $societeB;

    protected SiteDepartement $sdA1;

    protected SiteDepartement $sdA2;

    protected SiteDepartement $sdB1;

    protected function creerOrganisation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->societeA = Societe::query()->create(['name' => 'Société A', 'code' => 'SA']);
        $this->societeB = Societe::query()->create(['name' => 'Société B', 'code' => 'SB']);

        $siteUn = SiteAffectation::query()->create(['name' => 'Site 1']);
        $siteDeux = SiteAffectation::query()->create(['name' => 'Site 2']);

        $deptA1 = Departement::query()->create(['name' => 'Dept A1', 'societe_id' => $this->societeA->id]);
        $deptA2 = Departement::query()->create(['name' => 'Dept A2', 'societe_id' => $this->societeA->id]);
        $deptB1 = Departement::query()->create(['name' => 'Dept B1', 'societe_id' => $this->societeB->id]);

        $this->sdA1 = $this->lier($siteUn, $deptA1);
        $this->sdA2 = $this->lier($siteDeux, $deptA2);
        $this->sdB1 = $this->lier($siteUn, $deptB1);
    }

    protected function lier(SiteAffectation $site, Departement $departement): SiteDepartement
    {
        return SiteDepartement::query()->create([
            'site_id' => $site->id,
            'societe_id' => $departement->societe_id,
            'departement_id' => $departement->id,
        ]);
    }

    /**
     * @param  list<SiteDepartement>  $perimetre
     */
    protected function utilisateur(RoleName $role, array $perimetre = []): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $user->siteDepartements()->sync(array_map(static fn (SiteDepartement $sd): int => $sd->id, $perimetre));

        return $user->fresh();
    }

    protected function employeDans(SiteDepartement $sd): Employe
    {
        // Embauche fixe : les tests utilisent des dates d'effet fixes (2026), une date aléatoire les rendrait intermittents.
        return Employe::factory()->pourSiteDepartement($sd)->create(['date_embauche' => '2020-01-01']);
    }
}
