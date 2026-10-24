<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\RoleName;
use App\Enums\WorkflowStatut;
use App\Filament\Resources\Conges\CongeResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Employes\EmployeResource;
use App\Filament\Resources\Employes\Pages\CreateEmploye;
use App\Filament\Resources\Employes\Pages\EditEmploye;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use App\Filament\Resources\Mutations\MutationResource;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\Sanctions\SanctionResource;
use App\Models\Employe;
use App\Models\ModelEvaluation;
use App\Models\NiveauSanction;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class InterfaceModulesRhTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private Employe $employe;

    private Employe $employeAilleurs;

    private User $senior;

    private User $officer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->seederModules();
        $this->employe = $this->employeDans($this->sdA1);
        $this->employeAilleurs = $this->employeDans($this->sdB1);
        $this->senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1, $this->sdA2]);
        $this->officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $this->admin = $this->utilisateur(RoleName::Admin);
    }

    /** @return class-string<ModuleRhResource> */
    private function ressource(ModuleRh $module): string
    {
        return match ($module) {
            ModuleRh::Document => DocumentResource::class,
            ModuleRh::Conge => CongeResource::class,
            ModuleRh::Sanction => SanctionResource::class,
            ModuleRh::Evaluation => EvaluationResource::class,
            ModuleRh::Promotion => PromotionResource::class,
            ModuleRh::Mutation => MutationResource::class,
        };
    }

    /** @return array<string, mixed> */
    private function champs(ModuleRh $module, Employe $employe): array
    {
        [$type, $modele] = $this->deuxTypes($module);

        $communs = ['matricule' => $employe->matricule, $module->cleType() => $type->id, $module->cleModele() => $modele->id];

        return $communs + match ($module) {
            ModuleRh::Document => ['date_production' => '2026-03-01'],
            ModuleRh::Conge => ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-06', 'nbre_jours' => 5],
            ModuleRh::Sanction => [
                'niveau_sanction_id' => NiveauSanction::query()->value('id'),
                'date_sanction' => '2026-03-02',
                'description' => 'Retards répétés.',
            ],
            ModuleRh::Evaluation => ['date_evaluation' => '2026-03-02'],
            ModuleRh::Promotion => ['nouvelle_fonction' => 'Chef d\'équipe', 'date_effet' => '2026-04-01'],
            ModuleRh::Mutation => ['nouveau_site_id' => $this->sdA2->site_id, 'nouveau_departement_id' => $this->sdA2->departement_id, 'nouvelle_societe_id' => $this->sdA2->societe_id, 'date_effet' => '2026-04-01'],
        };
    }

    private function brouillon(ModuleRh $module, User $auteur): Model
    {
        $this->actingAs($auteur);
        [$type, $modele] = $this->deuxTypes($module);

        return $this->creerEnregistrement($module, $this->employe, $type, $modele);
    }

    // ------------------------------------------------------------- accès

    #[DataProvider('modules')]
    public function test_les_pages_de_liste_et_de_creation_s_affichent(ModuleRh $module): void
    {
        $this->actingAs($this->admin);
        $ressource = $this->ressource($module);

        $this->get($ressource::getUrl('index'))->assertOk();
        $this->get($ressource::getUrl('create'))->assertOk();
    }

    public function test_les_sanctions_sont_inaccessibles_a_un_officer_meme_dans_son_perimetre(): void
    {
        $sanction = $this->brouillon(ModuleRh::Sanction, $this->senior);

        $this->actingAs($this->officer);
        $this->get(SanctionResource::getUrl('index'))->assertForbidden();
        $this->get(SanctionResource::getUrl('view', ['record' => $sanction]))->assertForbidden();
        $this->get(DocumentResource::getUrl('index'))->assertOk();
    }

    public function test_la_liste_est_limitee_au_perimetre(): void
    {
        $dedans = $this->brouillon(ModuleRh::Document, $this->admin);
        $this->actingAs($this->admin);
        [$type, $modele] = $this->deuxTypes(ModuleRh::Document);
        $dehors = $this->creerEnregistrement(ModuleRh::Document, $this->employeAilleurs, $type, $modele);

        $this->actingAs($this->officer);
        Livewire::test(\App\Filament\Resources\Documents\Pages\ListDocuments::class)
            ->assertCanSeeTableRecords([$dedans])
            ->assertCanNotSeeTableRecords([$dehors]);

        $this->get(DocumentResource::getUrl('view', ['record' => $dehors]))->assertNotFound();
    }

    // ------------------------------------------------------------- création

    #[DataProvider('modules')]
    public function test_creation_depuis_le_formulaire_donne_un_brouillon_sans_reference(ModuleRh $module): void
    {
        $this->actingAs($this->senior);

        $classePage = $this->ressource($module)::getPages()['create']->getPage();

        $champs = $this->champs($module, $this->employe);
        $test = Livewire::test($classePage)->fillForm($champs);
        if ($module === ModuleRh::Mutation) {
            // Changer la société réinitialise le département (comportement voulu) : on le resaisit.
            $test->fillForm(['nouveau_departement_id' => $champs['nouveau_departement_id']]);
        }
        if ($module === ModuleRh::Evaluation) {
            // Le choix du modèle affiche ses critères : chaque note est obligatoire (Phase 7).
            $test->fillForm(['notes_criteres' => array_map(
                static fn (array $c): array => ['libelle' => $c['libelle'], 'note' => 12],
                ModelEvaluation::query()->findOrFail($champs['model_evaluation_id'])->criteres,
            )]);
        }
        $test
            ->call('create')
            ->assertHasNoFormErrors();

        $enregistrement = $module->enregistrementClass()::query()->firstOrFail();
        if ($module === ModuleRh::Evaluation) {
            $this->assertEquals(12.0, $enregistrement->note);
        }
        $this->assertSame($this->employe->matricule, $enregistrement->matricule);
        $this->assertNull($enregistrement->reference);
        $this->assertSame(WorkflowStatut::Brouillon, $enregistrement->workflow->statut);
        $this->assertSame($this->senior->id, $enregistrement->workflow->cree_par_id);
    }

    public function test_un_modele_d_un_autre_type_est_refuse_par_le_formulaire(): void
    {
        $this->actingAs($this->senior);
        [, , $typeB, $modeleB] = $this->deuxTypes(ModuleRh::Document);
        [$typeA] = $this->deuxTypes(ModuleRh::Document);

        Livewire::test(DocumentResource::getPages()['create']->getPage())
            ->fillForm(['matricule' => $this->employe->matricule, 'type_document_id' => $typeA->id, 'model_document_id' => $modeleB->id, 'date_production' => '2026-03-01'])
            ->call('create')
            ->assertHasFormErrors(['model_document_id']);

        $this->assertSame(0, \App\Models\Document::query()->count());
    }

    public function test_creation_pour_un_agent_hors_perimetre_est_refusee(): void
    {
        $this->actingAs($this->senior);

        Livewire::test(DocumentResource::getPages()['create']->getPage())
            ->fillForm($this->champs(ModuleRh::Document, $this->employeAilleurs))
            ->call('create');

        $this->assertSame(0, \App\Models\Document::query()->count());
    }

    public function test_mutation_vers_un_site_hors_perimetre_est_refusee(): void
    {
        $this->actingAs($this->officer);
        $champs = $this->champs(ModuleRh::Mutation, $this->employe);

        Livewire::test(MutationResource::getPages()['create']->getPage())->fillForm($champs)
            ->fillForm(['nouveau_departement_id' => $champs['nouveau_departement_id']])->call('create');

        $this->assertSame(0, \App\Models\Mutation::query()->count(), 'L\'officer n\'a pas sdA2 dans son périmètre.');
    }

    public function test_les_champs_salaire_sont_masques_sans_la_permission(): void
    {
        $this->actingAs($this->senior);
        Livewire::test(PromotionResource::getPages()['create']->getPage())
            ->assertFormFieldHidden('ancien_salaire')
            ->assertFormFieldHidden('nouveau_salaire');

        $this->actingAs($this->admin);
        Livewire::test(PromotionResource::getPages()['create']->getPage())
            ->assertFormFieldVisible('ancien_salaire');
    }

    // ------------------------------------------------------------- modification

    public function test_un_brouillon_se_modifie_puis_se_fige_apres_soumission(): void
    {
        $document = $this->brouillon(ModuleRh::Document, $this->officer);

        $this->actingAs($this->officer);
        Livewire::test(DocumentResource::getPages()['edit']->getPage(), ['record' => $document->getKey()])
            ->fillForm(['commentaire' => 'Mis à jour'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Mis à jour', $document->fresh()->commentaire);

        app(WorkflowService::class)->soumettre($document, $this->officer);

        $this->get(DocumentResource::getUrl('edit', ['record' => $document]))->assertForbidden();
    }

    // ------------------------------------------------------------- workflow dans l'interface

    public function test_boutons_du_workflow_selon_les_droits_et_la_separation(): void
    {
        $document = $this->brouillon(ModuleRh::Document, $this->officer);
        $vue = DocumentResource::getPages()['view']->getPage();

        $this->actingAs($this->officer);
        Livewire::test($vue, ['record' => $document->getKey()])
            ->assertActionVisible('workflow_SOUMETTRE')
            ->assertActionHidden('workflow_VALIDER')
            ->assertActionHidden('workflow_APPROUVER')
            ->callAction('workflow_SOUMETTRE');

        $this->assertSame(WorkflowStatut::DocumentSoumis, $document->fresh()->workflow->statut);

        // L'auteur ne voit pas les boutons de traitement de son propre enregistrement.
        Livewire::test($vue, ['record' => $document->getKey()])->assertActionHidden('workflow_PRENDRE_EN_CHARGE');

        // Un autre compte habilité les voit.
        $this->actingAs($this->senior);
        Livewire::test($vue, ['record' => $document->getKey()])
            ->assertActionVisible('workflow_PRENDRE_EN_CHARGE')
            ->callAction('workflow_PRENDRE_EN_CHARGE');
        $this->assertSame(WorkflowStatut::EnAttenteValidation, $document->fresh()->workflow->statut);
    }

    public function test_circuit_complet_depuis_l_interface_et_reference_a_l_approbation(): void
    {
        $document = $this->brouillon(ModuleRh::Document, $this->officer);
        $vue = DocumentResource::getPages()['view']->getPage();

        $this->actingAs($this->officer);
        Livewire::test($vue, ['record' => $document->getKey()])->callAction('workflow_SOUMETTRE');

        $this->actingAs($this->senior);
        foreach (['PRENDRE_EN_CHARGE', 'VALIDER', 'TRANSMETTRE'] as $etape) {
            Livewire::test($vue, ['record' => $document->getKey()])->callAction('workflow_'.$etape);
        }
        $this->assertNull($document->fresh()->reference);

        // Le senior n'a pas le droit d'approuver : le bouton est absent.
        Livewire::test($vue, ['record' => $document->getKey()])->assertActionHidden('workflow_APPROUVER');

        $this->actingAs($this->admin);
        Livewire::test($vue, ['record' => $document->getKey()])->callAction('workflow_APPROUVER');

        $this->assertSame('0001/DA_DARH/DLS/'.now()->year, $document->fresh()->reference);
        $this->assertSame(WorkflowStatut::Approuve, $document->fresh()->workflow->statut);
    }

    public function test_le_rejet_exige_un_motif_dans_l_interface(): void
    {
        $document = $this->brouillon(ModuleRh::Document, $this->officer);
        app(WorkflowService::class)->soumettre($document, $this->officer);
        $vue = DocumentResource::getPages()['view']->getPage();

        $this->actingAs($this->senior);
        Livewire::test($vue, ['record' => $document->getKey()])
            ->callAction('workflow_REJETER', ['commentaire' => ''])
            ->assertHasActionErrors(['commentaire' => 'required']);
        $this->assertSame(WorkflowStatut::DocumentSoumis, $document->fresh()->workflow->statut);

        Livewire::test($vue, ['record' => $document->getKey()])
            ->callAction('workflow_REJETER', ['commentaire' => 'Pièce manquante.']);
        $this->assertSame(WorkflowStatut::Rejete, $document->fresh()->workflow->statut);
    }

    public function test_un_bouton_visible_ne_remplace_pas_le_controle_serveur(): void
    {
        // Même en forçant l'appel d'une action masquée, le service refuse.
        $document = $this->brouillon(ModuleRh::Document, $this->officer);
        app(WorkflowService::class)->soumettre($document, $this->officer);
        $vue = DocumentResource::getPages()['view']->getPage();

        $this->actingAs($this->officer);
        Livewire::test($vue, ['record' => $document->getKey()])->assertActionHidden('workflow_PRENDRE_EN_CHARGE');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(WorkflowService::class)->prendreEnCharge($document, $this->officer);
    }

    // ------------------------------------------------------------- agents

    public function test_liste_des_agents_limitee_au_perimetre(): void
    {
        $this->actingAs($this->officer);
        Livewire::test(EmployeResource::getPages()['index']->getPage())
            ->assertCanSeeTableRecords([$this->employe])
            ->assertCanNotSeeTableRecords([$this->employeAilleurs]);
    }

    public function test_creation_d_un_agent_avec_son_affectation(): void
    {
        $this->actingAs($this->senior);

        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'matricule' => 'M-NEW-1', 'name' => 'Nouvel Agent', 'date_embauche' => '2026-01-05',
                'site_departement_id' => $this->sdA2->id, 'fonction' => 'Technicien',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $agent = Employe::query()->where('matricule', 'M-NEW-1')->firstOrFail();
        $this->assertSame($this->sdA2->site_id, $agent->site_id);
        $this->assertSame(1, $agent->affectations()->count());
    }

    public function test_creation_d_un_agent_hors_perimetre_refusee(): void
    {
        $this->actingAs($this->officer);

        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'matricule' => 'M-NEW-2', 'name' => 'Hors Périmètre', 'date_embauche' => '2026-01-05',
                'site_departement_id' => $this->sdB1->id,
            ])
            ->call('create');

        $this->assertDatabaseMissing('employes', ['matricule' => 'M-NEW-2']);
    }

    public function test_l_edition_d_un_agent_ne_change_jamais_l_affectation(): void
    {
        $this->actingAs($this->senior);
        $avant = $this->employe->only(['societe_id', 'site_id', 'departement_id', 'fonction']);

        Livewire::test(EditEmploye::class, ['record' => $this->employe->getKey()])
            ->fillForm(['name' => 'Nom modifié', 'telephone' => '77000000'])
            ->call('save')
            ->assertHasNoFormErrors();

        $apres = $this->employe->fresh();
        $this->assertSame('Nom modifié', $apres->name);
        $this->assertSame($avant, $apres->only(['societe_id', 'site_id', 'departement_id', 'fonction']));
    }

    public function test_dossier_d_un_agent_hors_perimetre_introuvable(): void
    {
        $this->actingAs($this->officer);

        $this->get(EmployeResource::getUrl('view', ['record' => $this->employeAilleurs]))->assertNotFound();
    }
}
