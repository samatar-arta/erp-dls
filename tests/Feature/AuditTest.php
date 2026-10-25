<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\RoleName;
use App\Exceptions\RegleMetierException;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Employe;
use App\Models\ModelDocument;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EmployeService;
use App\Services\UserManagementService;
use App\Services\WorkflowService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private User $admin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->admin = $this->utilisateur(RoleName::Admin);
        $this->manager = $this->utilisateur(RoleName::RhManager);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dernier(string $action): ?AuditLog
    {
        return AuditLog::query()->where('action', $action)->orderByDesc('id')->first();
    }

    // ------------------------------------------------------------- journal

    public function test_le_journal_est_immuable_pour_tous_les_roles(): void
    {
        $log = app(AuditService::class)->journaliser('test.action', null, 'Essai.');

        foreach ([$this->admin, $this->manager] as $utilisateur) {
            $this->actingAs($utilisateur);

            try {
                $log->fresh()->forceFill(['description' => 'Falsifié'])->save();
                $this->fail('La modification aurait dû être refusée.');
            } catch (RegleMetierException) {
                $this->addToAssertionCount(1);
            }

            try {
                $log->fresh()->delete();
                $this->fail('La suppression aurait dû être refusée.');
            } catch (RegleMetierException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Essai.', $log->fresh()->description);
        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class); // aucun champ assignable en masse
        (new AuditLog)->fill(['action' => 'x']);
    }

    public function test_les_donnees_sensibles_sont_retirees(): void
    {
        $log = app(AuditService::class)->journaliser('test.sensible', null, 'Essai.', [
            'champs' => ['name'],
            'password' => 'secret123',
            'Num_CNI' => 'A123',
            'detail' => ['telephone' => '77000000', 'ancien_salaire' => 1000, 'site_id' => 3],
        ]);

        $this->assertSame(['champs' => ['name'], 'detail' => ['site_id' => 3]], $log->fresh()->donnees);
    }

    public function test_l_acteur_est_pris_sur_l_utilisateur_connecte_et_son_nom_est_fige(): void
    {
        $this->actingAs($this->admin);
        $log = app(AuditService::class)->journaliser('test.acteur');

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($this->admin->name, $log->acteur);

        $ancienNom = $this->admin->name;
        $this->admin->forceFill(['name' => 'Autre nom'])->save();

        $this->assertSame($ancienNom, $log->fresh()->acteur, 'Le nom de l\'acteur est figé dans l\'entrée.');
    }

    // ------------------------------------------------------------- utilisateurs

    public function test_les_changements_de_roles_sont_journalises_avec_l_avant_et_l_apres(): void
    {
        $cible = $this->utilisateur(RoleName::RhJunior, [$this->sdA1]);

        app(UserManagementService::class)->mettreAJourRoles($this->admin, $cible, [RoleName::RhSenior->value]);

        $log = $this->dernier('utilisateur.roles_modifies');
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame((string) $cible->id, $log->cible_id);
        $this->assertSame([RoleName::RhJunior->value], $log->donnees['avant']);
        $this->assertSame([RoleName::RhSenior->value], $log->donnees['apres']);
    }

    public function test_un_changement_de_roles_refuse_ne_laisse_aucune_trace(): void
    {
        $avant = AuditLog::query()->count();

        try {
            app(UserManagementService::class)->mettreAJourRoles($this->admin, $this->admin, [RoleName::RhJunior->value]);
        } catch (\Throwable) {
        }

        // Dernier compte global : retirer son rôle est refusé et rien n'est journalisé.
        $unique = $this->manager;
        $this->admin->forceFill(['is_active' => false])->saveQuietly();
        try {
            app(UserManagementService::class)->mettreAJourRoles($this->utilisateur(RoleName::RhSenior), $unique, [RoleName::RhJunior->value]);
        } catch (RegleMetierException) {
        }

        $this->assertNull($this->dernier('utilisateur.roles_modifies'));
        $this->assertGreaterThanOrEqual($avant, AuditLog::query()->count());
    }

    public function test_perimetre_et_activation_sont_journalises(): void
    {
        $cible = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $service = app(UserManagementService::class);

        $service->mettreAJourAffectations($this->admin, $cible, [$this->sdA2->id]);
        $perimetre = $this->dernier('utilisateur.perimetre_modifie');
        $this->assertSame([$this->sdA1->id], $perimetre->donnees['avant']);
        $this->assertSame([$this->sdA2->id], $perimetre->donnees['apres']);

        $service->definirActif($this->admin, $cible, false);
        $this->assertNotNull($this->dernier('utilisateur.desactive'));
        $service->definirActif($this->admin, $cible, true);
        $this->assertNotNull($this->dernier('utilisateur.active'));
    }

    public function test_creation_et_modification_de_compte_sans_jamais_le_mot_de_passe(): void
    {
        $this->actingAs($this->admin);
        $utilisateur = User::factory()->create(['name' => 'Nouvel Agent']);
        $this->assertSame((string) $utilisateur->id, $this->dernier('utilisateur.cree')->cible_id);

        $utilisateur->forceFill(['password' => Hash::make('Un-Autre-Mot-De-Passe-1!')])->save();
        $log = $this->dernier('utilisateur.modifie');
        $this->assertSame(['password'], $log->donnees['champs']);
        $this->assertStringNotContainsString('Mot-De-Passe', json_encode($log->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_connexions_et_echecs_sont_journalises(): void
    {
        $this->post('/erp/login');

        Event::dispatch(new Failed('web', null, ['email' => 'inconnu@example.test', 'password' => 'motdepasse']));

        $log = $this->dernier('auth.echec');
        $this->assertSame(['email' => 'inconnu@example.test'], $log->donnees);
        $this->assertStringNotContainsString('motdepasse', json_encode($log->toArray(), JSON_THROW_ON_ERROR));

        $this->actingAs($this->admin);
        event(new \Illuminate\Auth\Events\Login('web', $this->admin, false));
        $this->assertSame($this->admin->id, $this->dernier('auth.connexion')->user_id);
    }

    // ------------------------------------------------------------- agents

    public function test_creation_et_changement_d_affectation_d_un_agent_sont_journalises(): void
    {
        $this->actingAs($this->admin);
        $service = app(EmployeService::class);

        $employe = $service->creer([
            'matricule' => 'M900001', 'name' => 'Agent Audit', 'fonction' => 'Technicien',
            'date_embauche' => '2024-01-02', 'statut_employe' => 'actif', 'handicap' => false,
            'societe_id' => $this->sdA1->societe_id, 'site_id' => $this->sdA1->site_id, 'departement_id' => $this->sdA1->departement_id,
        ], $this->admin);

        $creation = $this->dernier('employe.cree');
        $this->assertSame('M900001', $creation->cible_id, 'L\'agent est identifié par son matricule.');

        $service->changerAffectation($employe, [
            'societe_id' => $this->sdA2->societe_id, 'site_id' => $this->sdA2->site_id, 'departement_id' => $this->sdA2->departement_id,
        ], Carbon::parse('2025-01-01'), 'mutation', $this->admin);

        $log = $this->dernier('employe.affectation_changee');
        $this->assertSame('mutation', $log->donnees['source']);
        $this->assertSame($this->sdA2->site_id, $log->donnees['site_id']);
        $this->assertNull($this->dernier('employe.modifie'), 'Un changement d\'affectation n\'est pas journalisé deux fois.');
    }

    public function test_la_modification_d_une_fiche_ne_journalise_que_les_noms_de_champs(): void
    {
        $this->actingAs($this->admin);
        $employe = $this->employeDans($this->sdA1);

        $employe->update(['telephone' => '77123456', 'num_CNI' => 'CNI-SECRET', 'niveau_etude' => 'Licence']);

        $log = $this->dernier('employe.modifie');
        $this->assertEqualsCanonicalizing(['telephone', 'num_CNI', 'niveau_etude'], $log->donnees['champs']);
        $json = json_encode($log->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('77123456', $json);
        $this->assertStringNotContainsString('CNI-SECRET', $json);
    }

    // ------------------------------------------------------------- workflow, téléchargements, modèles

    public function test_chaque_action_du_workflow_est_journalisee_avec_la_reference_a_l_approbation(): void
    {
        $this->seederModules();
        $employe = $this->employeDans($this->sdA1);
        $officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $service = app(WorkflowService::class);

        $this->actingAs($officer);
        [$type, $modele] = $this->deuxTypes(ModuleRh::Document);
        $document = $this->creerEnregistrement(ModuleRh::Document, $employe, $type, $modele);

        $service->soumettre($document, $officer);
        $service->prendreEnCharge($document, $senior);
        $service->valider($document, $senior);
        $service->transmettre($document, $senior);
        $service->approuver($document, $this->admin);

        $this->assertSame(
            ['workflow.soumettre', 'workflow.prendre_en_charge', 'workflow.valider', 'workflow.transmettre', 'workflow.approuver'],
            AuditLog::query()->where('action', 'like', 'workflow.%')->orderBy('id')->pluck('action')->all(),
        );
        $approbation = $this->dernier('workflow.approuver');
        $this->assertSame($this->admin->id, $approbation->user_id);
        $this->assertSame($document->fresh()->reference, $approbation->donnees['reference']);
        $this->assertSame($employe->matricule, $approbation->donnees['matricule']);
    }

    public function test_une_transition_refusee_ne_laisse_aucune_trace(): void
    {
        $this->seederModules();
        $employe = $this->employeDans($this->sdA1);
        $officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);

        $this->actingAs($officer);
        [$type, $modele] = $this->deuxTypes(ModuleRh::Document);
        $document = $this->creerEnregistrement(ModuleRh::Document, $employe, $type, $modele);

        try {
            app(WorkflowService::class)->approuver($document, $this->admin);
        } catch (RegleMetierException) {
        }

        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'workflow.%')->count());
    }

    public function test_la_modification_d_un_modele_est_journalisee_sans_son_contenu(): void
    {
        $this->seederModules();
        $this->actingAs($this->admin);
        $modele = ModelDocument::query()->firstOrFail();

        $modele->update(['titre' => 'Nouveau titre']);

        $log = $this->dernier('modele.modifie');
        $this->assertSame(ModuleRh::Document->value, $log->donnees['module']);
        $this->assertContains('titre', $log->donnees['champs']);
        $this->assertArrayNotHasKey('contenu', $log->donnees);

        $avant = AuditLog::query()->count();
        $modele->fresh()->save();
        $this->assertSame($avant, AuditLog::query()->count(), 'Une sauvegarde sans changement ne journalise rien.');
    }

    public function test_les_ajustements_de_solde_sont_journalises(): void
    {
        $this->seederModules();
        $this->actingAs($this->admin);
        $employe = $this->employeDans($this->sdA1);
        $type = \App\Models\TypeConge::query()->where('code', 'ANNUEL')->firstOrFail();
        $solde = \App\Models\SoldeConge::query()->forceCreate(['matricule' => $employe->matricule, 'type_conge_id' => $type->id, 'annee' => 2026, 'jours_acquis' => 30])->fresh();

        $solde->update(['jours_report' => 4]);

        $log = $this->dernier('solde_conge.modifie');
        $this->assertEquals(0, $log->donnees['avant']['jours_report']);
        $this->assertEquals(4, $log->donnees['apres']['jours_report']);
    }

    // ------------------------------------------------------------- consultation

    public function test_seuls_admin_et_rh_manager_consultent_le_journal(): void
    {
        $senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $log = app(AuditService::class)->journaliser('test.lecture');

        foreach ([$this->admin, $this->manager] as $global) {
            $this->assertTrue(Gate::forUser($global)->allows('viewAny', AuditLog::class));
            $this->assertTrue(Gate::forUser($global)->allows('view', $log));
        }
        $this->assertFalse(Gate::forUser($senior)->allows('viewAny', AuditLog::class));

        foreach ([$this->admin, $this->manager] as $global) {
            $this->assertFalse(Gate::forUser($global)->allows('create', AuditLog::class));
            $this->assertFalse(Gate::forUser($global)->allows('update', $log));
            $this->assertFalse(Gate::forUser($global)->allows('delete', $log));
        }
    }

    public function test_l_ecran_du_journal_est_reserve_et_en_lecture_seule(): void
    {
        $senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);

        $this->actingAs($senior);
        $this->get(AuditLogResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->manager);
        $this->get(AuditLogResource::getUrl('index'))->assertOk();
        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertArrayNotHasKey('create', AuditLogResource::getPages());
    }
}
