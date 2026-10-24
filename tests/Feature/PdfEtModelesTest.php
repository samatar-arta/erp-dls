<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\RoleName;
use App\Exceptions\RegleMetierException;
use App\Filament\Resources\Rh\Modeles\ModelDocumentResource;
use App\Filament\Resources\Rh\Modeles\Pages\CreateModelDocument;
use App\Filament\Resources\Rh\Modeles\Pages\ListModelDocuments;
use App\Filament\Resources\Rh\Types\TypeDocumentResource;
use App\Jobs\GenererPdfJob;
use App\Models\Document;
use App\Models\Employe;
use App\Models\ModelDocument;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\EnregistrementRhService;
use App\Services\GenerationPdfService;
use App\Services\Signature\SignatureInterne;
use App\Services\Signature\SignatureProviderInterface;
use App\Services\TemplateRendererService;
use App\Services\WorkflowService;
use App\Support\VariablesModele;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class PdfEtModelesTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private Employe $employe;

    private User $auteur;

    private User $valideur;

    private User $admin;

    private WorkflowService $workflow;

    private GenerationPdfService $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Queue::fake();
        $this->creerOrganisation();
        $this->seederModules();
        $this->employe = $this->employeDans($this->sdA1);
        $this->auteur = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $this->valideur = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $this->admin = $this->utilisateur(RoleName::Admin);
        $this->workflow = app(WorkflowService::class);
        $this->pdf = app(GenerationPdfService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function brouillon(ModuleRh $module): Model
    {
        $this->actingAs($this->auteur);
        [$type, $modele] = $this->deuxTypes($module);

        return $this->creerEnregistrement($module, $this->employe, $type, $modele);
    }

    private function approuve(ModuleRh $module): Model
    {
        $objet = $this->brouillon($module);
        $this->workflow->soumettre($objet, $this->auteur);
        $this->workflow->prendreEnCharge($objet, $this->valideur);
        $this->workflow->valider($objet, $this->valideur);
        $this->workflow->transmettre($objet, $this->valideur);
        $this->workflow->approuver($objet, $this->admin);

        return $objet->fresh();
    }

    // ----------------------------------------------------------- moteur de rendu

    #[DataProvider('modules')]
    public function test_les_modeles_seedes_se_rendent_sans_variable_residuelle(ModuleRh $module): void
    {
        $objet = $this->approuve($module);
        $modele = $module->modeleClass()::query()->findOrFail($objet->{$module->cleModele()});

        $html = app(TemplateRendererService::class)->rendre($objet, $modele->contenu);

        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringContainsString($objet->reference, $html);
        $this->assertStringContainsString($this->employe->name, $html);
        $this->assertStringContainsString('Approuvé électroniquement', $html);
    }

    public function test_les_valeurs_sont_echappees_et_le_modele_n_est_jamais_execute(): void
    {
        $this->employe->forceFill(['name' => '<script>alert(1)</script> Dupont'])->save();
        $objet = $this->brouillon(ModuleRh::Document);

        $html = app(TemplateRendererService::class)->rendre(
            $objet->fresh(),
            "{{ employe.name }} @php echo 1; @endphp {{ system('id') }} <?php echo 'x'; ?> {!! \$x !!} {{ document.reference }}",
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Dupont', $html);
        $this->assertStringContainsString('@php echo 1; @endphp', $html, 'Blade n\'est pas compilé.');
        $this->assertStringNotContainsString('<?php', $html);
        $this->assertStringContainsString('{{ system(\'id\') }}', $html, 'Une expression non conforme reste du texte inerte.');
        $this->assertStringContainsString('(référence en attente)', $html);
    }

    public function test_une_variable_hors_liste_blanche_donne_une_valeur_vide(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);

        $html = app(TemplateRendererService::class)->rendre($objet->fresh(), 'A[{{ employe.num_CNSS }}][{{ employe.password }}]B');

        $this->assertStringContainsString('A[][]B', $html);
    }

    public function test_la_liste_blanche_ne_contient_aucune_donnee_sensible(): void
    {
        foreach (ModuleRh::cases() as $module) {
            foreach (VariablesModele::pour($module) as $nom => $chemin) {
                $this->assertDoesNotMatchRegularExpression('/salaire|CNSS|CNI|password|telephone|adresse/i', $nom.$chemin);
            }
        }
    }

    public function test_l_apercu_remplace_les_variables_par_leur_nom(): void
    {
        $html = app(TemplateRendererService::class)->apercu(ModuleRh::Conge, '{{ conge.date_debut }} {{ inconnue }}');

        $this->assertStringContainsString('[conge.date_debut]', $html);
        $this->assertStringContainsString('variable inconnue : inconnue', $html);
    }

    // ----------------------------------------------------------- enregistrement des modèles

    public function test_un_modele_avec_variable_non_autorisee_est_refuse(): void
    {
        $type = TypeDocument::query()->firstOrFail();

        $this->expectException(RegleMetierException::class);
        ModelDocument::query()->create(['type_document_id' => $type->id, 'titre' => 'X', 'contenu' => 'Salaire : {{ promotion.nouveau_salaire }}']);
    }

    public function test_un_modele_avec_variables_valides_est_accepte(): void
    {
        $type = TypeDocument::query()->firstOrFail();

        $modele = ModelDocument::query()->create(['type_document_id' => $type->id, 'titre' => 'X', 'contenu' => '{{ employe.name }} — {{ document.reference }} {{ signature }}']);

        $this->assertTrue($modele->exists);
    }

    // ----------------------------------------------------------- génération PDF

    public function test_l_approbation_planifie_le_pdf_et_le_job_est_envoye(): void
    {
        $objet = $this->approuve(ModuleRh::Document);

        $this->assertSame('en_attente', $objet->statut_generation);
        Queue::assertPushed(GenererPdfJob::class, fn (GenererPdfJob $j): bool => $j->module === 'DOCUMENT' && $j->id === $objet->id);
    }

    public function test_aucun_pdf_n_est_planifie_avant_l_approbation(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);
        $this->workflow->soumettre($objet, $this->auteur);

        Queue::assertNothingPushed();
        $this->assertNull($objet->fresh()->statut_generation);
    }

    #[DataProvider('modules')]
    public function test_generation_du_pdf_pour_chaque_module(ModuleRh $module): void
    {
        $objet = $this->approuve($module);

        $this->assertTrue($this->pdf->generer($objet));

        $objet = $objet->fresh();
        $this->assertSame('genere', $objet->statut_generation);
        Storage::disk('private')->assertExists($objet->fichier_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($objet->fichier_path));
        $this->assertSame(hash('sha256', Storage::disk('private')->get($objet->fichier_path)), $objet->empreinte_pdf);
        $this->assertNotNull($objet->contenu_genere);
        $this->assertSame(1, $objet->version_modele);
        $this->assertTrue($this->pdf->pdfValide($objet));
    }

    public function test_la_generation_est_idempotente_et_la_reference_ne_change_pas(): void
    {
        $objet = $this->approuve(ModuleRh::Document);
        $reference = $objet->reference;

        $this->assertTrue($this->pdf->generer($objet));
        $apres = $objet->fresh();
        $empreinte = $apres->empreinte_pdf;
        $genereLe = $apres->genere_le;

        $this->assertFalse($this->pdf->generer($objet->fresh()), 'Le second appel ne régénère rien.');
        (new GenererPdfJob('DOCUMENT', $objet->id))->handle($this->pdf);

        $final = $objet->fresh();
        $this->assertSame($reference, $final->reference);
        $this->assertSame($empreinte, $final->empreinte_pdf);
        $this->assertEquals($genereLe, $final->genere_le);
        $this->assertCount(1, Storage::disk('private')->allFiles('documents-generes'));
        $this->assertSame(1, \DB::table('reference_sequences')->where('module', 'DOCUMENT')->value('dernier_numero'));
    }

    public function test_le_contenu_est_fige_meme_si_le_modele_change_ensuite(): void
    {
        $objet = $this->approuve(ModuleRh::Document);
        $this->pdf->generer($objet);
        $fige = $objet->fresh()->contenu_genere;

        ModelDocument::query()->findOrFail($objet->model_document_id)->update(['contenu' => 'Totalement différent {{ employe.name }}']);
        // Le fichier est perdu : la régénération reprend le contenu figé, pas le nouveau modèle.
        Storage::disk('private')->delete($objet->fresh()->fichier_path);

        $this->assertTrue($this->pdf->generer($objet->fresh()));
        $this->assertSame($fige, $objet->fresh()->contenu_genere);
        $this->assertSame(1, $objet->fresh()->version_modele);
    }

    public function test_un_fichier_altere_est_detecte_et_regenere_a_l_identique(): void
    {
        $objet = $this->approuve(ModuleRh::Document);
        $this->pdf->generer($objet);
        $chemin = $objet->fresh()->fichier_path;
        Storage::disk('private')->put($chemin, 'contenu falsifié');

        $this->assertFalse($this->pdf->pdfValide($objet->fresh()));

        $this->assertTrue($this->pdf->generer($objet->fresh()));
        $this->assertTrue($this->pdf->pdfValide($objet->fresh()));
    }

    public function test_pas_de_pdf_pour_un_enregistrement_non_approuve(): void
    {
        $objet = $this->brouillon(ModuleRh::Document);

        $this->expectException(RegleMetierException::class);
        $this->pdf->generer($objet);
    }

    public function test_une_generation_en_echec_peut_etre_relancee(): void
    {
        $objet = $this->approuve(ModuleRh::Document);
        $objet->forceFill(['statut_generation' => 'echec'])->save();

        $this->assertTrue($this->pdf->generer($objet->fresh()));
        $this->assertSame('genere', $objet->fresh()->statut_generation);
    }

    public function test_dompdf_n_autorise_ni_php_ni_ressources_distantes(): void
    {
        $this->assertFalse((bool) config('dompdf.options.enable_php'));
        $this->assertFalse((bool) config('dompdf.options.enable_remote'));
    }

    // ----------------------------------------------------------- signature

    public function test_la_signature_interne_est_verifiable_et_liee_a_l_enregistrement(): void
    {
        $this->assertInstanceOf(SignatureInterne::class, app(SignatureProviderInterface::class));
        $a = $this->approuve(ModuleRh::Document);
        $b = $this->approuve(ModuleRh::Document);
        $signature = new SignatureInterne;

        $empreinte = $signature->empreinte($a);

        $this->assertTrue($signature->verifier($a, $empreinte));
        $this->assertFalse($signature->verifier($b, $empreinte));
        $this->assertStringContainsString($this->admin->name, $signature->bloc($a));
    }

    public function test_pas_de_signature_avant_l_approbation(): void
    {
        $this->assertSame('', (new SignatureInterne)->bloc($this->brouillon(ModuleRh::Document)->fresh()));
    }

    // ----------------------------------------------------------- téléchargement

    public function test_telechargement_du_pdf_autorise(): void
    {
        $objet = $this->approuve(ModuleRh::Document);
        $this->pdf->generer($objet);

        $this->actingAs($this->valideur)
            ->get(route('enregistrements.pdf', ['DOCUMENT', $objet->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_telechargement_refuse_hors_perimetre_et_sans_connexion(): void
    {
        $objet = $this->approuve(ModuleRh::Document);
        $this->pdf->generer($objet);
        $url = route('enregistrements.pdf', ['DOCUMENT', $objet->id]);

        auth()->logout(); // les helpers de test ont authentifié un utilisateur
        $this->get($url)->assertRedirect();

        $horsPerimetre = $this->utilisateur(RoleName::RhSenior, [$this->sdB1]);
        $this->actingAs($horsPerimetre)->get($url)->assertForbidden();
    }

    public function test_pdf_de_sanction_inaccessible_a_un_officer(): void
    {
        $sanction = $this->approuve(ModuleRh::Sanction);
        $this->pdf->generer($sanction);
        $officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);

        $this->actingAs($officer)->get(route('enregistrements.pdf', ['SANCTION', $sanction->id]))->assertForbidden();
    }

    public function test_telechargement_404_si_pdf_absent_ou_altere(): void
    {
        $objet = $this->approuve(ModuleRh::Document);
        $url = route('enregistrements.pdf', ['DOCUMENT', $objet->id]);

        $this->actingAs($this->valideur)->get($url)->assertNotFound();

        $this->pdf->generer($objet);
        Storage::disk('private')->put($objet->fresh()->fichier_path, 'falsifié');
        $this->actingAs($this->valideur)->get($url)->assertNotFound();
    }

    public function test_module_inconnu_donne_404(): void
    {
        $this->actingAs($this->admin)->get('/rh/PAYLIST/1/pdf')->assertNotFound();
    }

    // ----------------------------------------------------------- délai de renouvellement

    private function service(): EnregistrementRhService
    {
        return app(EnregistrementRhService::class);
    }

    /** @return array<string, mixed> */
    private function donneesDocument(string $code, string $date): array
    {
        $type = TypeDocument::query()->where('code', $code)->firstOrFail();
        $modele = ModelDocument::query()->where('type_document_id', $type->id)->firstOrFail();

        return ['matricule' => $this->employe->matricule, 'type_document_id' => $type->id, 'model_document_id' => $modele->id, 'date_production' => $date];
    }

    public function test_delai_de_renouvellement_d_une_attestation(): void
    {
        $this->service()->creer(ModuleRh::Document, $this->donneesDocument('ATTESTATION_TRAVAIL', '2026-03-01'), $this->auteur);

        try {
            $this->service()->creer(ModuleRh::Document, $this->donneesDocument('ATTESTATION_TRAVAIL', '2026-04-15'), $this->auteur);
            $this->fail('Le délai de 90 jours aurait dû bloquer.');
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString('90 jours', $e->getMessage());
        }

        $apres = $this->service()->creer(ModuleRh::Document, $this->donneesDocument('ATTESTATION_TRAVAIL', '2026-06-01'), $this->auteur);
        $this->assertTrue($apres->exists);
    }

    public function test_un_type_sans_delai_n_est_pas_limite(): void
    {
        $this->service()->creer(ModuleRh::Document, $this->donneesDocument('ATTESTATION_CONGE', '2026-03-01'), $this->auteur);
        $second = $this->service()->creer(ModuleRh::Document, $this->donneesDocument('ATTESTATION_CONGE', '2026-03-02'), $this->auteur);

        $this->assertTrue($second->exists);
    }

    public function test_un_document_rejete_ne_compte_pas_dans_le_delai(): void
    {
        $premier = $this->service()->creer(ModuleRh::Document, $this->donneesDocument('CERTIFICAT_TRAVAIL', '2026-03-01'), $this->auteur);
        $this->workflow->soumettre($premier, $this->auteur);
        $this->workflow->rejeter($premier, $this->valideur, 'Erreur de saisie.');

        $second = $this->service()->creer(ModuleRh::Document, $this->donneesDocument('CERTIFICAT_TRAVAIL', '2026-03-05'), $this->auteur);

        $this->assertTrue($second->exists);
        $this->assertSame(2, Document::query()->count());
    }

    public function test_le_delai_est_propre_a_chaque_agent(): void
    {
        $this->service()->creer(ModuleRh::Document, $this->donneesDocument('ATTESTATION_TRAVAIL', '2026-03-01'), $this->auteur);
        $autre = $this->employeDans($this->sdA1);
        $donnees = ['matricule' => $autre->matricule] + $this->donneesDocument('ATTESTATION_TRAVAIL', '2026-03-02');

        $this->assertTrue($this->service()->creer(ModuleRh::Document, $donnees, $this->auteur)->exists);
    }

    // ----------------------------------------------------------- administration des types et modèles

    public function test_admin_acces_aux_ecrans_de_types_et_de_modeles(): void
    {
        $this->actingAs($this->admin);

        $this->get(TypeDocumentResource::getUrl('index'))->assertOk();
        $this->get(TypeDocumentResource::getUrl('create'))->assertOk();
        $this->get(ModelDocumentResource::getUrl('index'))->assertOk();
        $this->get(ModelDocumentResource::getUrl('create'))->assertOk();
    }

    public function test_un_senior_lit_les_types_mais_ne_peut_ni_creer_ni_modifier(): void
    {
        $this->actingAs($this->auteur);

        $this->get(TypeDocumentResource::getUrl('index'))->assertOk();
        $this->get(TypeDocumentResource::getUrl('create'))->assertForbidden();
        $this->get(ModelDocumentResource::getUrl('create'))->assertForbidden();
    }

    public function test_creation_d_un_modele_depuis_l_interface_et_rejet_d_une_variable_inconnue(): void
    {
        $this->actingAs($this->admin);
        $type = TypeDocument::query()->firstOrFail();

        Livewire::test(CreateModelDocument::class)
            ->fillForm(['type_document_id' => $type->id, 'titre' => 'Mauvais', 'contenu' => '{{ employe.salaire }}', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['contenu']);
        $this->assertDatabaseMissing('model_documents', ['titre' => 'Mauvais']);

        Livewire::test(CreateModelDocument::class)
            ->fillForm(['type_document_id' => $type->id, 'titre' => 'Bon', 'contenu' => '<p>{{ employe.name }}</p><script>alert(1)</script>', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $cree = ModelDocument::query()->where('titre', 'Bon')->firstOrFail();
        $this->assertStringNotContainsString('<script', $cree->contenu);
        $this->assertSame($this->admin->id, $cree->cree_par);
    }

    public function test_apercu_d_un_modele_depuis_la_liste(): void
    {
        $this->actingAs($this->admin);
        $modele = ModelDocument::query()->firstOrFail();

        Livewire::test(ListModelDocuments::class)
            ->callTableAction('apercu', $modele)
            ->assertHasNoTableActionErrors();
    }
}
