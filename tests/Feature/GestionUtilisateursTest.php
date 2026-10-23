<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Exceptions\RegleMetierException;
use App\Services\UserManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class GestionUtilisateursTest extends TestCase
{
    use CreeOrganisation, RefreshDatabase;

    private UserManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->service = app(UserManagementService::class);
    }

    public function test_un_utilisateur_ne_modifie_pas_ses_propres_roles(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $this->utilisateur(RoleName::RhManager);

        $this->expectException(AuthorizationException::class);
        $this->service->mettreAJourRoles($admin, $admin, [RoleName::RhJunior->value]);
    }

    public function test_un_utilisateur_ne_modifie_pas_ses_propres_affectations(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);

        $this->expectException(AuthorizationException::class);
        $this->service->mettreAJourAffectations($admin, $admin, [$this->sdA1->id]);
        $this->assertFalse($admin->can('updateRoles', $admin));
    }

    public function test_un_admin_modifie_les_roles_et_affectations_d_un_autre(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $cible = $this->utilisateur(RoleName::RhJunior);

        $this->service->mettreAJourRoles($admin, $cible, [RoleName::RhSenior->value]);
        $this->service->mettreAJourAffectations($admin, $cible, [$this->sdA1->id, $this->sdA2->id]);

        $this->assertTrue($cible->fresh()->hasRole(RoleName::RhSenior->value));
        $this->assertCount(2, $cible->fresh()->siteDepartements);
    }

    public function test_le_dernier_admin_actif_ne_peut_pas_etre_desactive(): void
    {
        $dernier = $this->utilisateur(RoleName::Admin);
        $autre = $this->utilisateur(RoleName::RhOfficer);

        $this->expectException(RegleMetierException::class);
        $this->service->definirActif($autre, $dernier, false);
    }

    public function test_le_dernier_admin_ne_peut_pas_perdre_son_role(): void
    {
        $dernier = $this->utilisateur(RoleName::RhManager);
        $acteur = $this->utilisateur(RoleName::RhOfficer);

        try {
            $this->service->mettreAJourRoles($acteur, $dernier, [RoleName::RhJunior->value]);
            $this->fail('Le retrait du dernier rôle global aurait dû être refusé.');
        } catch (RegleMetierException) {
            $this->assertTrue($dernier->fresh()->hasGlobalAccess());
        }
    }

    public function test_le_dernier_admin_ne_peut_pas_etre_supprime_ni_desactive_directement(): void
    {
        $dernier = $this->utilisateur(RoleName::Admin);

        try {
            $dernier->delete();
            $this->fail('Suppression du dernier admin non bloquée.');
        } catch (RegleMetierException) {
            $this->assertTrue($dernier->fresh()->is_active);
        }

        $this->expectException(RegleMetierException::class);
        $dernier->update(['is_active' => false]);
    }

    public function test_un_admin_peut_etre_desactive_s_il_en_reste_un_autre(): void
    {
        $premier = $this->utilisateur(RoleName::Admin);
        $second = $this->utilisateur(RoleName::RhManager);

        $this->service->definirActif($second, $premier, false);

        $this->assertFalse($premier->fresh()->is_active);
    }

    public function test_un_utilisateur_ne_se_desactive_pas_lui_meme(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $this->utilisateur(RoleName::Admin);

        $this->expectException(RegleMetierException::class);
        $this->service->definirActif($admin, $admin, false);
    }

    public function test_role_inconnu_refuse(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $cible = $this->utilisateur(RoleName::RhJunior);

        $this->expectException(RegleMetierException::class);
        $this->service->mettreAJourRoles($admin, $cible, ['SUPER_ADMIN']);
    }
}
