<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\Departements\DepartementResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\SiteAffectations\SiteAffectationResource;
use App\Filament\Resources\SiteDepartements\Pages\CreateSiteDepartement;
use App\Filament\Resources\SiteDepartements\SiteDepartementResource;
use App\Filament\Resources\Societes\SocieteResource;
use App\Filament\Resources\TypeContrats\TypeContratResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class InterfaceFilamentTest extends TestCase
{
    use CreeOrganisation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
    }

    public function test_page_de_connexion_accessible(): void
    {
        $this->get('/erp/login')->assertOk();
    }

    public function test_admin_et_rh_manager_acces_a_toutes_les_pages_de_liste(): void
    {
        foreach (RoleName::globaux() as $role) {
            $this->actingAs($this->utilisateur($role));
            foreach ([SocieteResource::class, SiteAffectationResource::class, DepartementResource::class,
                SiteDepartementResource::class, TypeContratResource::class, GradeResource::class, UserResource::class] as $resource) {
                $this->get($resource::getUrl('index'))->assertOk();
            }
        }
    }

    public function test_rh_senior_lit_l_organisation_mais_n_administre_pas(): void
    {
        $this->actingAs($this->utilisateur(RoleName::RhSenior, [$this->sdA1]));

        $this->get(SocieteResource::getUrl('index'))->assertOk();
        $this->get(SocieteResource::getUrl('create'))->assertForbidden();
        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(GradeResource::getUrl('create'))->assertForbidden();
    }

    public function test_compte_desactive_n_accede_pas_au_panneau(): void
    {
        $user = $this->utilisateur(RoleName::Admin);
        $user->forceFill(['is_active' => false])->saveQuietly();

        $this->actingAs($user->fresh())->get('/erp')->assertForbidden();
    }

    public function test_creation_d_un_utilisateur_avec_roles_et_perimetre(): void
    {
        $this->actingAs($this->utilisateur(RoleName::Admin));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Awa Mohamed',
                'email' => 'awa@dls.test',
                'password' => 'Str0ng!Passw0rd#',
                'is_active' => true,
                'roles' => [RoleName::RhSenior->value],
                'site_departement_ids' => [$this->sdA1->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $awa = User::query()->where('email', 'awa@dls.test')->firstOrFail();
        $this->assertTrue($awa->hasRole('RH_SENIOR'));
        $this->assertSame([$this->sdA1->id], $awa->siteDepartements()->pluck('site_departements.id')->all());
    }

    public function test_mot_de_passe_faible_refuse(): void
    {
        $this->actingAs($this->utilisateur(RoleName::Admin));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Faible', 'email' => 'faible@dls.test', 'password' => 'abc',
                'roles' => [RoleName::RhJunior->value],
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_edition_depuis_l_interface_ne_permet_pas_de_desactiver_le_dernier_admin(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $autre = $this->utilisateur(RoleName::RhOfficer);
        $this->actingAs($autre);

        // L'acteur n'est pas ADMIN : l'accès à la page d'édition lui est déjà refusé.
        $this->get(UserResource::getUrl('edit', ['record' => $admin]))->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_un_admin_ne_change_pas_ses_propres_roles_via_l_interface(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $this->utilisateur(RoleName::RhManager);
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->fillForm(['name' => 'Nouveau nom', 'roles' => [RoleName::RhJunior->value]])
            ->call('save');

        $admin = $admin->fresh();
        $this->assertSame('Nouveau nom', $admin->name);
        $this->assertTrue($admin->hasRole('ADMIN'));
        $this->assertFalse($admin->hasRole('RH_JUNIOR'));
    }

    public function test_un_site_departement_incoherent_est_refuse_par_le_formulaire(): void
    {
        $this->actingAs($this->utilisateur(RoleName::Admin));
        $deptB = \App\Models\Departement::query()->where('societe_id', $this->societeB->id)->firstOrFail();

        Livewire::test(CreateSiteDepartement::class)
            ->fillForm([
                'site_id' => $this->sdA2->site_id,
                'societe_id' => $this->societeA->id,
                'departement_id' => $deptB->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['departement_id']);
    }
}
