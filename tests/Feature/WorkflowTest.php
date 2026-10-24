<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\RoleName;
use App\Enums\WorkflowAction;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Models\Employe;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowHistory;
use App\Services\WorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private WorkflowService $service;

    private Employe $employe;

    private User $officer;

    private User $senior;

    private User $seniorAuteur;

    private User $admin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->seederModules();
        $this->service = app(WorkflowService::class);
        $this->employe = $this->employeDans($this->sdA1);
        $this->officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $this->senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $this->seniorAuteur = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $this->admin = $this->utilisateur(RoleName::Admin);
        $this->manager = $this->utilisateur(RoleName::RhManager);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Les sanctions sont à accès renforcé : leur auteur est un RH_SENIOR (différent du valideur). */
    private function auteurPour(ModuleRh|Model $module): User
    {
        $module = $module instanceof Model ? ModuleRh::pourEnregistrement($module) : $module;

        return $module === ModuleRh::Sanction ? $this->seniorAuteur : $this->officer;
    }

    private function brouillon(ModuleRh $module, ?User $auteur = null): Model
    {
        $this->actingAs($auteur ?? $this->auteurPour($module));
        [$type, $modele] = $this->deuxTypes($module);

        return $this->creerEnregistrement($module, $this->employe, $type, $modele);
    }

    private function jusquAApprobation(Model $objet, ?User $validateur = null): void
    {
        $this->service->soumettre($objet, $this->auteurPour($objet));
        $this->service->prendreEnCharge($objet, $validateur ?? $this->senior);
        $this->service->valider($objet, $validateur ?? $this->senior);
        $this->service->transmettre($objet, $validateur ?? $this->senior);
    }

    // ------------------------------------------------------- morph map / création

    public function test_morph_map_des_six_modules(): void
    {
        $map = Relation::morphMap();
        foreach (ModuleRh::cases() as $module) {
            $this->assertSame($module->enregistrementClass(), $map[$module->alias()]);
        }
    }

    #[DataProvider('modules')]
    public function test_creer_un_enregistrement_cree_un_workflow_brouillon_sans_reference(ModuleRh $module): void
    {
        $objet = $this->brouillon($module);

        $workflow = Workflow::query()->where('workflowable_type', $module->alias())->where('workflowable_id', $objet->id)->first();
        $this->assertNotNull($workflow);
        $this->assertSame(WorkflowStatut::Brouillon, $workflow->statut);
        $this->assertSame($this->auteurPour($module)->id, $workflow->cree_par_id);
        $this->assertNull($objet->fresh()->reference);
        $this->assertTrue($objet->workflow->is($workflow));
    }

    public function test_les_tables_metier_ne_portent_pas_de_statut_de_workflow(): void
    {
        foreach (ModuleRh::cases() as $module) {
            $this->assertFalse(\Schema::hasColumn((new ($module->enregistrementClass()))->getTable(), 'statut'));
        }
    }

    // ------------------------------------------------------- circuit complet

    #[DataProvider('modules')]
    public function test_circuit_complet_la_reference_naît_a_l_approbation_finale(ModuleRh $module): void
    {
        $objet = $this->brouillon($module);

        $this->service->soumettre($objet, $this->auteurPour($module));
        $this->assertNull($objet->reference);
        $this->service->prendreEnCharge($objet, $this->senior);
        $this->service->valider($objet, $this->senior);
        $this->assertNull($objet->reference, 'Pas de référence à la validation.');
        $this->service->transmettre($objet, $this->senior);
        $this->assertNull($objet->reference);

        $workflow = $this->service->approuver($objet, $this->admin);

        $this->assertSame(WorkflowStatut::Approuve, $workflow->statut);
        $this->assertSame('0001/'.$module->prefixeParDefaut().'/'.now()->year, $objet->fresh()->reference);
        $this->assertSame($this->admin->id, $workflow->approuve_par_id);
        $this->assertNull($workflow->echeance_at);
        $this->assertSame(
            ['SOUMETTRE', 'PRENDRE_EN_CHARGE', 'VALIDER', 'TRANSMETTRE', 'APPROUVER'],
            $workflow->historiques->map(fn (WorkflowHistory $h) => $h->action->value)->all(),
        );
    }

    public function test_deux_types_du_meme_module_partagent_le_meme_compteur(): void
    {
        [$typeA, $modeleA, $typeB, $modeleB] = $this->deuxTypes(ModuleRh::Document);
        $this->actingAs($this->officer);
        $a = $this->creerEnregistrement(ModuleRh::Document, $this->employe, $typeA, $modeleA);
        $b = $this->creerEnregistrement(ModuleRh::Document, $this->employe, $typeB, $modeleB);
        $this->jusquAApprobation($a);
        $this->jusquAApprobation($b);

        $this->service->approuver($a, $this->admin);
        $this->service->approuver($b, $this->manager);

        $this->assertStringStartsWith('0001/', $a->fresh()->reference);
        $this->assertStringStartsWith('0002/', $b->fresh()->reference);
    }

    public function test_la_reference_est_independante_de_l_identifiant_technique(): void
    {
        $premier = $this->brouillon(ModuleRh::Conge);
        $second = $this->brouillon(ModuleRh::Conge);
        $this->assertLessThan($second->id, $premier->id);

        // Deux congés du même agent ne peuvent pas se chevaucher (Phase 6).
        $second->update(['date_debut' => '2026-04-06', 'date_fin' => '2026-04-10']);

        // On approuve d'abord le second : il reçoit 0001.
        $this->jusquAApprobation($second);
        $this->service->approuver($second, $this->admin);
        $this->jusquAApprobation($premier);
        $this->service->approuver($premier, $this->admin);

        $this->assertStringStartsWith('0001/', $second->fresh()->reference);
        $this->assertStringStartsWith('0002/', $premier->fresh()->reference);
    }

    public function test_rejet_n_attribue_pas_de_reference_et_ne_consomme_aucun_numero(): void
    {
        $rejete = $this->brouillon(ModuleRh::Sanction);
        $this->service->soumettre($rejete, $this->auteurPour(ModuleRh::Sanction));
        $this->service->rejeter($rejete, $this->senior, 'Dossier incomplet.');
        $this->assertNull($rejete->fresh()->reference);

        $suivant = $this->brouillon(ModuleRh::Sanction);
        $this->jusquAApprobation($suivant);
        $this->service->approuver($suivant, $this->admin);

        $this->assertStringStartsWith('0001/', $suivant->fresh()->reference);
    }

    // ------------------------------------------------------- matrice

    public function test_transitions_interdites_depuis_chaque_statut(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);

        foreach ([WorkflowAction::PrendreEnCharge, WorkflowAction::Valider, WorkflowAction::Transmettre, WorkflowAction::Approuver, WorkflowAction::Rouvrir] as $action) {
            try {
                $this->service->executer($action, $objet, $this->admin);
                $this->fail("{$action->value} depuis BROUILLON aurait dû être refusé.");
            } catch (RegleMetierException $e) {
                $this->assertStringContainsString('Transition impossible', $e->getMessage());
            }
        }
        $this->assertSame(WorkflowStatut::Brouillon, $objet->fresh()->workflow->statut);
    }

    public function test_on_ne_peut_pas_sauter_la_validation(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);

        $this->expectException(RegleMetierException::class);
        $this->service->approuver($objet, $this->admin);
    }

    public function test_un_enregistrement_approuve_est_fige(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->jusquAApprobation($objet);
        $this->service->approuver($objet, $this->admin);

        foreach (WorkflowAction::cases() as $action) {
            if ($action === WorkflowAction::Expirer) {
                continue;
            }
            try {
                $this->service->executer($action, $objet, $this->manager, 'x');
                $this->fail("{$action->value} après approbation aurait dû être refusé.");
            } catch (RegleMetierException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_approuver_deux_fois_avec_un_objet_perime_ne_double_pas_la_reference(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->jusquAApprobation($objet);
        $copiePerimee = $objet->fresh();

        $this->service->approuver($objet, $this->admin);
        $reference = $objet->fresh()->reference;

        try {
            $this->service->approuver($copiePerimee, $this->manager);
            $this->fail('Seconde approbation acceptée.');
        } catch (RegleMetierException) {
        }

        $this->assertSame($reference, $objet->fresh()->reference);
        $this->assertSame(1, \DB::table('reference_sequences')->where('module', 'DOCUMENT')->value('dernier_numero'));
    }

    public function test_rejet_exige_un_commentaire(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);

        $this->expectException(RegleMetierException::class);
        $this->service->rejeter($objet, $this->senior, '   ');
    }

    public function test_rejet_puis_reouverture_puis_nouveau_cycle(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);
        $this->service->rejeter($objet, $this->senior, 'À corriger.');
        $this->assertSame(WorkflowStatut::Rejete, $objet->workflow->statut);

        $this->service->rouvrir($objet, $this->officer);
        $this->assertSame(WorkflowStatut::Brouillon, $objet->workflow->statut);

        $this->jusquAApprobation($objet);
        $this->service->approuver($objet, $this->admin);
        $this->assertNotNull($objet->fresh()->reference);
        $this->assertCount(8, $objet->workflow->historiques);
    }

    public function test_approbation_rejetee_exige_le_droit_d_approuver(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->jusquAApprobation($objet);

        $this->expectException(AuthorizationException::class);
        $this->service->rejeter($objet, $this->senior, 'Un senior ne rejette pas une approbation.');
    }

    // ------------------------------------------------------- séparation des responsabilités

    #[DataProvider('modules')]
    public function test_le_createur_ne_peut_pas_valider_son_enregistrement(ModuleRh $module): void
    {
        // Un RH_SENIOR crée, soumet, puis essaie de prendre en charge / valider lui-même.
        $objet = $this->brouillon($module, $this->senior);
        $this->service->soumettre($objet, $this->senior);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('Séparation des responsabilités');
        $this->service->prendreEnCharge($objet, $this->senior);
    }

    #[DataProvider('modules')]
    public function test_admin_ne_peut_pas_approuver_ce_qu_il_a_cree(ModuleRh $module): void
    {
        $objet = $this->brouillon($module, $this->admin);
        $this->service->soumettre($objet, $this->admin);
        $this->service->prendreEnCharge($objet, $this->senior);
        $this->service->valider($objet, $this->senior);
        $this->service->transmettre($objet, $this->senior);

        try {
            $this->service->approuver($objet, $this->admin);
            $this->fail('ADMIN a approuvé son propre enregistrement.');
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString('Séparation des responsabilités', $e->getMessage());
        }

        $this->assertNull($objet->fresh()->reference);

        // Un autre compte à droits identiques (RH_MANAGER) peut approuver.
        $this->service->approuver($objet, $this->manager);
        $this->assertNotNull($objet->fresh()->reference);
    }

    public function test_rh_manager_ne_peut_pas_approuver_ce_qu_il_a_soumis(): void
    {
        $objet = $this->brouillon(ModuleRh::Conge, $this->officer);
        $this->service->soumettre($objet, $this->manager);
        $this->service->prendreEnCharge($objet, $this->senior);
        $this->service->valider($objet, $this->senior);
        $this->service->transmettre($objet, $this->senior);

        $this->expectException(RegleMetierException::class);
        $this->service->approuver($objet, $this->manager);
    }

    public function test_option_approbateur_different_du_validateur(): void
    {
        config(['workflow.approbateur_different_validateur' => true]);
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);
        $this->service->prendreEnCharge($objet, $this->admin);
        $this->service->valider($objet, $this->admin);
        $this->service->transmettre($objet, $this->admin);

        try {
            $this->service->approuver($objet, $this->admin);
            $this->fail('Le validateur a approuvé alors que l\'option l\'interdit.');
        } catch (RegleMetierException) {
        }

        $this->service->approuver($objet, $this->manager);
        $this->assertNotNull($objet->fresh()->reference);
    }

    // ------------------------------------------------------- droits et périmètre

    public function test_rh_senior_valide_et_admin_approuve_mais_senior_n_approuve_pas(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->jusquAApprobation($objet);

        $this->assertFalse($this->service->peutExecuter(WorkflowAction::Approuver, $objet, $this->senior));
        $this->assertTrue($this->service->peutExecuter(WorkflowAction::Approuver, $objet, $this->admin));

        $this->expectException(AuthorizationException::class);
        $this->service->approuver($objet, $this->senior);
    }

    public function test_un_officer_ne_valide_pas(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);

        $this->expectException(AuthorizationException::class);
        $this->service->prendreEnCharge($objet, $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]));
    }

    public function test_hors_perimetre_refuse_meme_avec_le_droit(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);
        $autreSenior = $this->utilisateur(RoleName::RhSenior, [$this->sdA2]);

        $this->expectException(AuthorizationException::class);
        $this->service->prendreEnCharge($objet, $autreSenior);
    }

    public function test_utilisateur_inactif_refuse(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $inactif = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $inactif->forceFill(['is_active' => false])->save();

        $this->expectException(AuthorizationException::class);
        $this->service->soumettre($objet, $inactif->fresh());
    }

    public function test_un_utilisateur_ne_peut_pas_declencher_l_expiration(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);

        $this->expectException(AuthorizationException::class);
        $this->service->executer(WorkflowAction::Expirer, $objet, $this->admin);
    }

    // ------------------------------------------------------- échéances

    public function test_echeance_de_48_heures_a_la_soumission(): void
    {
        Carbon::setTestNow('2026-03-02 09:00:00');
        $objet = $this->brouillon(ModuleRh::Document);
        $workflow = $this->service->soumettre($objet, $this->officer);

        $this->assertTrue($workflow->echeance_at->equalTo(Carbon::parse('2026-03-04 09:00:00')));
    }

    public function test_expiration_des_enregistrements_en_retard(): void
    {
        Carbon::setTestNow('2026-03-02 09:00:00');
        $enRetard = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($enRetard, $this->officer);
        $this->service->prendreEnCharge($enRetard, $this->senior);

        Carbon::setTestNow('2026-03-04 08:59:00');
        $this->assertSame(0, $this->service->expirerEchus());

        Carbon::setTestNow('2026-03-04 09:00:01');
        $this->assertSame(1, $this->service->expirerEchus());

        $workflow = $enRetard->fresh()->workflow;
        $this->assertSame(WorkflowStatut::Expire, $workflow->statut);
        $this->assertNull($enRetard->fresh()->reference);
        $this->assertNull($workflow->historiques->last()->user_id);

        // Idempotent.
        $this->assertSame(0, $this->service->expirerEchus());
    }

    public function test_un_brouillon_n_expire_pas(): void
    {
        Carbon::setTestNow('2026-03-02 09:00:00');
        $this->brouillon(ModuleRh::Document);
        Carbon::setTestNow('2027-01-01');

        $this->assertSame(0, $this->service->expirerEchus());
    }

    // ------------------------------------------------------- immutabilité

    #[DataProvider('modules')]
    public function test_la_reference_est_immuable_pour_tous_les_roles(ModuleRh $module): void
    {
        $objet = $this->brouillon($module);
        $this->jusquAApprobation($objet);
        $this->service->approuver($objet, $this->admin);
        $reference = $objet->fresh()->reference;

        $this->actingAs($this->admin);

        foreach (['Autre', null] as $valeur) {
            $copie = $objet->fresh();
            $copie->forceFill(['reference' => $valeur]);
            try {
                $copie->save();
                $this->fail('Référence modifiée.');
            } catch (RegleMetierException $e) {
                $this->assertStringContainsString('définitive', $e->getMessage());
            }
        }

        $this->assertSame($reference, $objet->fresh()->reference);
    }

    public function test_la_reference_n_est_pas_assignable_en_masse(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $objet->fill(['reference' => '9999/FAUX/DLS/2026']);
        $objet->save();

        $this->assertNull($objet->fresh()->reference);
    }

    public function test_un_enregistrement_reference_ne_peut_pas_etre_supprime(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->jusquAApprobation($objet);
        $this->service->approuver($objet, $this->admin);

        $this->expectException(RegleMetierException::class);
        $objet->fresh()->delete();
    }

    public function test_un_enregistrement_soumis_ne_peut_pas_etre_supprime(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);

        $this->expectException(RegleMetierException::class);
        $objet->fresh()->delete();
    }

    public function test_un_brouillon_supprime_emporte_son_workflow(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $id = $objet->id;
        $objet->delete();

        $this->assertSame(0, Workflow::query()->where('workflowable_type', 'document')->where('workflowable_id', $id)->count());
    }

    public function test_l_historique_est_immuable_pour_tous_les_roles(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->service->soumettre($objet, $this->officer);
        $this->actingAs($this->admin);

        $ligne = WorkflowHistory::query()->firstOrFail();

        try {
            $ligne->forceFill(['commentaire' => 'réécrit'])->save();
            $this->fail('Historique modifié.');
        } catch (RegleMetierException) {
        }

        try {
            $ligne->delete();
            $this->fail('Historique supprimé.');
        } catch (RegleMetierException) {
        }

        $this->assertNull($ligne->fresh()->commentaire);
    }

    public function test_un_echec_de_transition_ne_laisse_aucune_trace(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $avant = WorkflowHistory::query()->count();

        try {
            $this->service->valider($objet, $this->senior);
        } catch (RegleMetierException) {
        }

        $this->assertSame($avant, WorkflowHistory::query()->count());
        $this->assertSame(WorkflowStatut::Brouillon, $objet->fresh()->workflow->statut);
    }

    public function test_permissions_du_workflow_par_role(): void
    {
        $this->assertTrue($this->admin->can('workflow.approuver'));
        $this->assertTrue($this->manager->can('workflow.approuver'));
        $this->assertTrue($this->admin->can('workflow.valider') && $this->manager->can('workflow.valider'));
        $this->assertTrue($this->senior->can('workflow.valider'));
        $this->assertFalse($this->senior->can('workflow.approuver'));
        $this->assertTrue($this->officer->can('workflow.soumettre'));
        $this->assertFalse($this->officer->can('workflow.valider'));
    }
}
