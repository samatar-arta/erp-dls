<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\RoleName;
use App\Exceptions\RegleMetierException;
use App\Models\Employe;
use App\Models\Promotion;
use App\Models\User;
use App\Services\EnregistrementRhService;
use App\Services\WorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class EnregistrementRhServiceTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private EnregistrementRhService $service;

    private Employe $employe;

    private User $senior;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->seederModules();
        $this->service = app(EnregistrementRhService::class);
        $this->employe = $this->employeDans($this->sdA1);
        $this->senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $this->admin = $this->utilisateur(RoleName::Admin);
    }

    /** @return array<string, mixed> */
    private function donnees(ModuleRh $module): array
    {
        [$type, $modele] = $this->deuxTypes($module);

        return $this->attributsEnregistrement($module, $this->employe, $type->id, $modele->id);
    }

    public function test_les_salaires_sont_ignores_sans_la_permission(): void
    {
        $donnees = $this->donnees(ModuleRh::Promotion) + ['ancien_salaire' => 100000, 'nouveau_salaire' => 150000];

        /** @var Promotion $parSenior */
        $parSenior = $this->service->creer(ModuleRh::Promotion, $donnees, $this->senior);
        $this->assertNull($parSenior->getRawOriginal('ancien_salaire'));

        /** @var Promotion $parAdmin */
        $parAdmin = $this->service->creer(ModuleRh::Promotion, $donnees, $this->admin);
        $this->assertEquals(150000, $parAdmin->fresh()->nouveau_salaire);
    }

    public function test_la_situation_actuelle_est_lue_sur_l_agent_pas_saisie(): void
    {
        $donnees = $this->donnees(ModuleRh::Mutation);
        $donnees['ancien_site_id'] = 99999;
        $donnees['nouvelle_fonction'] = 'Nouvelle fonction';

        $mutation = $this->service->creer(ModuleRh::Mutation, $donnees, $this->senior);

        $this->assertSame($this->employe->site_id, $mutation->ancien_site_id);
        $this->assertSame($this->employe->departement_id, $mutation->ancien_departement_id);
        $this->assertSame($this->employe->fonction, $mutation->ancienne_fonction);
    }

    public function test_une_mutation_sans_changement_est_refusee(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->service->creer(ModuleRh::Mutation, $this->donnees(ModuleRh::Mutation), $this->senior);
    }

    public function test_agent_sorti_refuse_sauf_pour_un_document(): void
    {
        $this->employe->update(['statut_employe' => 'sorti']);

        $this->assertNotNull($this->service->creer(ModuleRh::Document, $this->donnees(ModuleRh::Document), $this->senior));

        $this->expectException(RegleMetierException::class);
        $this->service->creer(ModuleRh::Conge, $this->donnees(ModuleRh::Conge), $this->senior);
    }

    public function test_creation_refusee_sans_le_droit_de_creer(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->service->creer(ModuleRh::Document, $this->donnees(ModuleRh::Document), User::factory()->create());
    }

    public function test_modification_refusee_apres_soumission(): void
    {
        $document = $this->service->creer(ModuleRh::Document, $this->donnees(ModuleRh::Document), $this->senior);
        $this->service->mettreAJour($document, ['commentaire' => 'ok'], $this->senior);

        app(WorkflowService::class)->soumettre($document, $this->senior);

        $this->expectException(AuthorizationException::class);
        $this->service->mettreAJour($document->fresh(), ['commentaire' => 'trop tard'], $this->senior);
    }

    public function test_l_agent_concerne_ne_peut_pas_etre_change(): void
    {
        $document = $this->service->creer(ModuleRh::Document, $this->donnees(ModuleRh::Document), $this->senior);
        $autre = $this->employeDans($this->sdA1);

        $this->service->mettreAJour($document, ['matricule' => $autre->matricule, 'commentaire' => 'x'], $this->senior);

        $this->assertSame($this->employe->matricule, $document->fresh()->matricule);
    }

    public function test_un_utilisateur_inactif_n_a_aucun_droit_sur_les_enregistrements(): void
    {
        $document = $this->service->creer(ModuleRh::Document, $this->donnees(ModuleRh::Document), $this->senior);
        $this->senior->forceFill(['is_active' => false])->save();
        $inactif = $this->senior->fresh();

        $this->assertFalse($inactif->can('view', $document));
        $this->assertFalse($inactif->can('update', $document));
        $this->assertFalse($inactif->can('viewAny', \App\Models\Document::class));
    }
}
