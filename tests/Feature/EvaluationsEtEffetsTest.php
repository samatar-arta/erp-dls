<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\RoleName;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Models\Employe;
use App\Models\Evaluation;
use App\Models\ModelEvaluation;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\User;
use App\Services\EffetsRhService;
use App\Services\EnregistrementRhService;
use App\Services\TemplateRendererService;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class EvaluationsEtEffetsTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private EnregistrementRhService $enregistrements;

    private WorkflowService $workflow;

    private Employe $employe;

    private User $officer;

    private User $senior;

    private User $admin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->seederModules();
        $this->enregistrements = app(EnregistrementRhService::class);
        $this->workflow = app(WorkflowService::class);
        $this->employe = $this->employeDans($this->sdA1);
        $this->officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $this->senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $this->admin = $this->utilisateur(RoleName::Admin);
        $this->manager = $this->utilisateur(RoleName::RhManager);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------- évaluations

    /** @return list<array{libelle: string, note: mixed}> */
    private function notes(mixed $qualite, mixed $delais, mixed $equipe, mixed $initiative): array
    {
        return [
            ['libelle' => 'Qualité du travail', 'note' => $qualite],
            ['libelle' => 'Respect des délais et ponctualité', 'note' => $delais],
            ['libelle' => 'Esprit d\'équipe', 'note' => $equipe],
            ['libelle' => 'Initiative et autonomie', 'note' => $initiative],
        ];
    }

    /** @param array<string, mixed> $en_plus */
    private function evaluation(array $notes, array $en_plus = [], ?ModelEvaluation $modele = null): Evaluation
    {
        [$type, $modeleParDefaut] = $this->deuxTypes(ModuleRh::Evaluation);
        $modele ??= $modeleParDefaut;

        /** @var Evaluation */
        return $this->enregistrements->creer(ModuleRh::Evaluation, [
            'matricule' => $this->employe->matricule,
            'type_evaluation_id' => $modele->type_evaluation_id,
            'model_evaluation_id' => $modele->id,
            'date_evaluation' => '2026-03-02',
            'notes_criteres' => $notes,
        ] + $en_plus, $this->officer);
    }

    public function test_la_note_finale_est_la_moyenne_ponderee_sur_20(): void
    {
        // (16×3 + 12×2 + 14×2 + 10×1) / 8 = 13,75
        $evaluation = $this->evaluation($this->notes(16, 12, 14, 10));

        $this->assertEquals(13.75, $evaluation->fresh()->note);
        $this->assertCount(4, $evaluation->fresh()->notes_criteres);
        $this->assertEquals(3, $evaluation->fresh()->notes_criteres[0]['coefficient']);
    }

    public function test_chaque_critere_est_ramene_sur_20_selon_son_bareme(): void
    {
        $modele = ModelEvaluation::query()->first();
        $modele->criteres = [
            ['libelle' => 'A', 'coefficient' => 1, 'note_max' => 10],
            ['libelle' => 'B', 'coefficient' => 1, 'note_max' => 20],
        ];
        $modele->save();

        $evaluation = $this->evaluation([['libelle' => 'A', 'note' => 5], ['libelle' => 'B', 'note' => 10]], [], $modele->fresh());

        $this->assertEquals(10.0, $evaluation->fresh()->note);
    }

    public function test_la_note_saisie_est_toujours_ecartee_au_profit_du_calcul(): void
    {
        $evaluation = $this->evaluation($this->notes(20, 20, 20, 20), ['note' => 1]);

        $this->assertEquals(20.0, $evaluation->fresh()->note);
        $this->assertNull((new Evaluation)->fill(['note' => 5])->note, 'La note n\'est pas assignable en masse.');
    }

    public function test_les_notes_manquantes_hors_bareme_ou_inconnues_sont_refusees(): void
    {
        $cas = [
            'manquante' => [[['libelle' => 'Qualité du travail', 'note' => 10]], 'obligatoire'],
            'hors barème' => [$this->notes(21, 10, 10, 10), 'comprise entre 0 et 20'],
            'négative' => [$this->notes(-1, 10, 10, 10), 'comprise entre 0 et 20'],
            'non numérique' => [$this->notes('abc', 10, 10, 10), 'obligatoire'],
            'critère inconnu' => [[...$this->notes(10, 10, 10, 10), ['libelle' => 'Inventé', 'note' => 5]], 'Critère inconnu'],
        ];

        foreach ($cas as $nom => [$notes, $message]) {
            try {
                $this->evaluation($notes);
                $this->fail("Le cas « {$nom} » aurait dû être refusé.");
            } catch (RegleMetierException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $nom);
            }
        }

        $this->assertSame(0, Evaluation::query()->count());
    }

    public function test_un_modele_sans_criteres_n_a_pas_de_note_chiffree(): void
    {
        $modele = ModelEvaluation::query()->first();
        $modele->criteres = null;
        $modele->save();

        $evaluation = $this->evaluation([['libelle' => 'x', 'note' => 5]], [], $modele->fresh());

        $this->assertNull($evaluation->fresh()->note);
        $this->assertNull($evaluation->fresh()->notes_criteres);
    }

    public function test_la_modification_d_un_brouillon_recalcule_la_note(): void
    {
        $evaluation = $this->evaluation($this->notes(10, 10, 10, 10));
        $this->assertEquals(10.0, $evaluation->fresh()->note);

        $this->enregistrements->mettreAJour($evaluation, ['notes_criteres' => $this->notes(20, 20, 20, 20), 'note' => 3], $this->officer);
        $this->assertEquals(20.0, $evaluation->fresh()->note);

        // Sans nouvelles notes, les notes enregistrées sont conservées.
        $this->enregistrements->mettreAJour($evaluation, ['appreciation' => 'Très bien.'], $this->officer);
        $this->assertEquals(20.0, $evaluation->fresh()->note);
    }

    public function test_le_detail_des_criteres_est_disponible_pour_les_modeles(): void
    {
        $evaluation = $this->evaluation($this->notes(16, 12, 14, 10));

        $html = app(TemplateRendererService::class)->rendre($evaluation->fresh(), 'Note {{ evaluation.note }} — {{ evaluation.criteres }}');

        $this->assertStringContainsString('Qualité du travail : 16/20 (coef. 3)', $html);
        $this->assertStringContainsString('13,75', str_replace('.', ',', $html));
    }

    public function test_les_ecrans_d_evaluation_s_affichent(): void
    {
        $evaluation = $this->evaluation($this->notes(16, 12, 14, 10));

        $this->actingAs($this->admin);
        $this->get(EvaluationResource::getUrl('create'))->assertOk();
        $this->get(EvaluationResource::getUrl('view', ['record' => $evaluation]))->assertOk()->assertSee('Qualité du travail');
        $this->get(EvaluationResource::getUrl('edit', ['record' => $evaluation]))->assertOk();
    }

    // ------------------------------------------------------------- effets des mutations / promotions

    /** @param array<string, mixed> $donnees */
    private function mutation(array $donnees = []): Mutation
    {
        [$type, $modele] = $this->deuxTypes(ModuleRh::Mutation);

        /** @var Mutation */
        return $this->enregistrements->creer(ModuleRh::Mutation, $donnees + [
            'matricule' => $this->employe->matricule,
            'type_mutation_id' => $type->id,
            'model_mutation_id' => $modele->id,
            'nouveau_site_id' => $this->sdA2->site_id,
            'nouveau_departement_id' => $this->sdA2->departement_id,
            'nouvelle_societe_id' => $this->sdA2->societe_id,
            'date_effet' => Carbon::today()->toDateString(),
        ], $this->admin);
    }

    /** @param array<string, mixed> $donnees */
    private function promotion(array $donnees = []): Promotion
    {
        [$type, $modele] = $this->deuxTypes(ModuleRh::Promotion);

        /** @var Promotion */
        return $this->enregistrements->creer(ModuleRh::Promotion, $donnees + [
            'matricule' => $this->employe->matricule,
            'type_promotion_id' => $type->id,
            'model_promotion_id' => $modele->id,
            'nouvelle_fonction' => 'Chef de service',
            'date_effet' => Carbon::today()->toDateString(),
        ], $this->admin);
    }

    /** L'auteur (admin) soumet ; un autre valide ; le manager approuve. */
    private function jusquAApprobationFinale(Model $objet): void
    {
        $this->workflow->soumettre($objet, $this->admin);
        $this->workflow->prendreEnCharge($objet, $this->senior);
        $this->workflow->valider($objet, $this->senior);
        $this->workflow->transmettre($objet, $this->senior);
    }

    private function approuver(Model $objet): void
    {
        $this->jusquAApprobationFinale($objet);
        $this->workflow->approuver($objet, $this->manager);
    }

    public function test_une_mutation_approuvee_change_l_affectation_et_garde_l_historique(): void
    {
        $mutation = $this->mutation();
        $this->approuver($mutation);

        $employe = $this->employe->fresh();
        $this->assertSame($this->sdA2->site_id, $employe->site_id);
        $this->assertSame($this->sdA2->departement_id, $employe->departement_id);
        $this->assertNotNull($mutation->fresh()->appliquee_le);

        $lignes = $employe->affectations()->reorder('id')->get();
        $this->assertCount(2, $lignes);
        $this->assertSame(Carbon::today()->toDateString(), $lignes[0]->date_fin->toDateString());
        $this->assertNull($lignes[1]->date_fin);
        $this->assertSame('mutation', $lignes[1]->source);
        $this->assertSame($this->sdA2->site_id, $lignes[1]->site_id);
    }

    public function test_une_mutation_sans_nouvelle_fonction_garde_la_fonction_actuelle(): void
    {
        $fonction = $this->employe->fonction;
        $this->approuver($this->mutation());

        $this->assertSame($fonction, $this->employe->fresh()->fonction);
    }

    public function test_une_promotion_approuvee_change_la_fonction_seulement(): void
    {
        $site = $this->employe->site_id;
        $this->approuver($this->promotion());

        $employe = $this->employe->fresh();
        $this->assertSame('Chef de service', $employe->fonction);
        $this->assertSame($site, $employe->site_id);
        $this->assertSame('promotion', $employe->affectations()->reorder('id', 'desc')->first()->source);
    }

    public function test_une_promotion_sans_changement_de_fonction_ne_cree_pas_d_affectation(): void
    {
        $promotion = $this->promotion(['nouvelle_fonction' => $this->employe->fonction]);
        $this->approuver($promotion);

        $this->assertCount(1, $this->employe->affectations);
        $this->assertNotNull($promotion->fresh()->appliquee_le);
    }

    public function test_rien_ne_change_avant_l_approbation_ni_apres_un_rejet(): void
    {
        $mutation = $this->mutation();
        $this->jusquAApprobationFinale($mutation);
        $this->assertSame($this->sdA1->site_id, $this->employe->fresh()->site_id);

        $this->workflow->rejeter($mutation, $this->manager, 'Refusée.');
        $this->assertSame($this->sdA1->site_id, $this->employe->fresh()->site_id);
        $this->assertNull($mutation->fresh()->appliquee_le);
    }

    public function test_un_effet_a_date_future_est_differe_puis_applique_une_seule_fois(): void
    {
        $dateEffet = Carbon::today()->addDays(10);
        $mutation = $this->mutation(['date_effet' => $dateEffet->toDateString()]);
        $this->approuver($mutation);

        $this->assertSame($this->sdA1->site_id, $this->employe->fresh()->site_id, 'Pas encore à la date d\'effet.');
        $this->assertNull($mutation->fresh()->appliquee_le);

        $effets = app(EffetsRhService::class);
        $this->assertSame(0, $effets->appliquerEchus());

        Carbon::setTestNow($dateEffet->copy()->addDay());
        $this->assertSame(1, $effets->appliquerEchus());
        $this->assertSame($this->sdA2->site_id, $this->employe->fresh()->site_id);
        $this->assertSame($dateEffet->toDateString(), $this->employe->affectations()->reorder('id')->first()->date_fin->toDateString());

        $this->assertSame(0, $effets->appliquerEchus(), 'Un effet n\'est jamais appliqué deux fois.');
        $this->assertCount(2, $this->employe->affectations);
    }

    public function test_la_commande_applique_les_effets_echus(): void
    {
        $mutation = $this->mutation(['date_effet' => Carbon::today()->addDays(3)->toDateString()]);
        $this->approuver($mutation);

        Carbon::setTestNow(Carbon::today()->addDays(4));
        $this->artisan('dls:appliquer-effets')->expectsOutput('1 effet(s) appliqué(s).')->assertSuccessful();
        $this->assertSame($this->sdA2->site_id, $this->employe->fresh()->site_id);
    }

    public function test_un_agent_sorti_entre_temps_annule_l_approbation(): void
    {
        $mutation = $this->mutation();
        $this->jusquAApprobationFinale($mutation);
        $this->employe->forceFill(['statut_employe' => 'sorti'])->save();

        try {
            $this->workflow->approuver($mutation, $this->manager);
            $this->fail('L\'approbation aurait dû être refusée.');
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString('plus en activité', $e->getMessage());
        }

        $mutation->refresh();
        $this->assertNull($mutation->reference);
        $this->assertNull($mutation->appliquee_le);
        $this->assertSame(WorkflowStatut::EnAttenteApprobation, $mutation->workflow->statut);
    }

    public function test_une_date_d_effet_anterieure_a_l_affectation_actuelle_est_refusee(): void
    {
        $debut = $this->employe->affectationCourante()->first()->date_debut;

        foreach ([fn () => $this->mutation(['date_effet' => $debut->copy()->subDay()->toDateString()]),
            fn () => $this->promotion(['date_effet' => $debut->copy()->subDay()->toDateString()])] as $creation) {
            try {
                $creation();
                $this->fail('La date d\'effet aurait dû être refusée.');
            } catch (RegleMetierException $e) {
                $this->assertStringContainsString('précéder', $e->getMessage());
            }
        }
    }

    public function test_les_effets_ne_sont_pas_assignables_en_masse(): void
    {
        $this->assertNull((new Mutation)->fill(['appliquee_le' => now()])->appliquee_le);
        $this->assertNull((new Promotion)->fill(['appliquee_le' => now()])->appliquee_le);
    }
}
