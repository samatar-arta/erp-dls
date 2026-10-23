<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employe;
use App\Models\Societe;
use App\Models\User;
use App\Policies\EmployePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class PerimetreEtPoliciesTest extends TestCase
{
    use CreeOrganisation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
    }

    public function test_rh_junior_ne_voit_pas_un_employe_hors_perimetre(): void
    {
        $junior = $this->utilisateur(RoleName::RhJunior, [$this->sdA1]);
        $dedans = $this->employeDans($this->sdA1);
        $dehors = $this->employeDans($this->sdA2);

        $this->assertTrue($junior->can('view', $dedans));
        $this->assertFalse($junior->can('view', $dehors));
        $this->assertSame([$dedans->id], Employe::query()->dansPerimetre($junior)->pluck('id')->all());
    }

    public function test_rh_senior_ne_modifie_pas_hors_perimetre(): void
    {
        $senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);

        $this->assertTrue($senior->can('update', $this->employeDans($this->sdA1)));
        $this->assertFalse($senior->can('update', $this->employeDans($this->sdB1)));
        $this->assertFalse($senior->can('manageFichiers', $this->employeDans($this->sdA2)));
    }

    public function test_perimetre_multiple_exclut_les_autres_departements_du_meme_site(): void
    {
        $user = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1, $this->sdA2]);

        $this->employeDans($this->sdA1);
        $this->employeDans($this->sdA2);
        // sdB1 est sur le même site que sdA1 mais dans un autre département/société : exclu.
        $this->employeDans($this->sdB1);

        $this->assertSame(2, Employe::query()->dansPerimetre($user)->count());
    }

    public function test_utilisateur_sans_affectation_ne_voit_rien(): void
    {
        $this->employeDans($this->sdA1);
        $user = $this->utilisateur(RoleName::RhOfficer);

        $this->assertSame(0, Employe::query()->dansPerimetre($user)->count());
    }

    public function test_admin_et_rh_manager_accedent_a_tous_les_dossiers(): void
    {
        foreach ([$this->sdA1, $this->sdA2, $this->sdB1] as $sd) {
            $this->employeDans($sd);
        }

        foreach (RoleName::globaux() as $role) {
            $user = $this->utilisateur($role);
            $this->assertTrue($user->hasGlobalAccess());
            $this->assertSame(3, Employe::query()->dansPerimetre($user)->count());
        }
    }

    public function test_les_autres_roles_n_ont_pas_acces_global(): void
    {
        foreach ([RoleName::RhSenior, RoleName::RhOfficer, RoleName::RhJunior] as $role) {
            $this->assertFalse($this->utilisateur($role)->hasGlobalAccess());
        }
    }

    public function test_admin_et_rh_manager_ont_des_resultats_identiques_sur_toutes_les_policies(): void
    {
        $employe = $this->employeDans($this->sdA2);
        $admin = $this->utilisateur(RoleName::Admin);
        $manager = $this->utilisateur(RoleName::RhManager);
        $autre = $this->utilisateur(RoleName::RhOfficer);

        $capacites = [
            ['viewAny', Employe::class], ['view', $employe], ['create', Employe::class],
            ['update', $employe], ['delete', $employe], ['viewFichiers', $employe], ['manageFichiers', $employe],
            ['viewAny', User::class], ['create', User::class], ['update', $autre],
            ['updateRoles', $autre], ['updateAffectations', $autre], ['delete', $autre], ['viewAny', Societe::class],
            ['create', Societe::class], ['update', $this->societeA], ['delete', $this->societeA],
        ];

        foreach ($capacites as [$capacite, $cible]) {
            $this->assertSame(
                $admin->can($capacite, $cible),
                $manager->can($capacite, $cible),
                "ADMIN et RH_MANAGER divergent sur {$capacite}",
            );
            $this->assertTrue($admin->can($capacite, $cible), "ADMIN devrait pouvoir {$capacite}");
        }

        $this->assertEqualsCanonicalizing(
            $admin->getAllPermissions()->pluck('name')->all(),
            $manager->getAllPermissions()->pluck('name')->all(),
        );
    }

    public function test_compte_desactive_n_a_plus_aucun_droit(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $admin->forceFill(['is_active' => false])->saveQuietly();

        $this->assertFalse($admin->fresh()->can('viewAny', Employe::class));
        $this->assertSame(0, Employe::query()->dansPerimetre($admin->fresh())->count());
    }

    public function test_les_roles_non_globaux_n_administrent_pas(): void
    {
        $senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);

        $this->assertFalse($senior->can('viewAny', User::class));
        $this->assertFalse($senior->can('create', Societe::class));
        $this->assertTrue($senior->can('viewAny', Societe::class));
        $this->assertInstanceOf(EmployePolicy::class, app(EmployePolicy::class));
    }
}
