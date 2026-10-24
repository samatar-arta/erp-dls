<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\RoleName;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Filament\Resources\JoursFeries\JourFerieResource;
use App\Filament\Resources\SoldesConges\SoldeCongeResource;
use App\Models\Conge;
use App\Models\Employe;
use App\Models\JourFerie;
use App\Models\SoldeConge;
use App\Models\TypeConge;
use App\Models\User;
use App\Services\CongeService;
use App\Services\EnregistrementRhService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class CongesTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private EnregistrementRhService $enregistrements;

    private WorkflowService $workflow;

    private CongeService $conges;

    private Employe $employe;

    private User $officer;

    private User $senior;

    private User $admin;

    private TypeConge $annuel;

    private TypeConge $exceptionnel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->seederModules();
        $this->enregistrements = app(EnregistrementRhService::class);
        $this->workflow = app(WorkflowService::class);
        $this->conges = app(CongeService::class);
        $this->employe = $this->employeDans($this->sdA1);
        $this->officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $this->senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $this->admin = $this->utilisateur(RoleName::Admin);
        $this->annuel = TypeConge::query()->where('code', 'ANNUEL')->firstOrFail();
        $this->exceptionnel = TypeConge::query()->where('code', 'EXCEPTIONNEL')->firstOrFail();
    }

    private function demande(TypeConge $type, string $debut, string $fin, array $en_plus = []): Conge
    {
        $modele = $type->modeleParDefaut();

        /** @var Conge */
        return $this->enregistrements->creer(ModuleRh::Conge, [
            'matricule' => $this->employe->matricule,
            'type_conge_id' => $type->id,
            'model_conge_id' => $modele->id,
            'date_debut' => $debut,
            'date_fin' => $fin,
        ] + $en_plus, $this->officer);
    }

    private function soumettre(Conge $conge): void
    {
        $this->workflow->soumettre($conge, $this->officer);
    }

    private function approuver(Conge $conge): void
    {
        $this->soumettre($conge);
        $this->workflow->prendreEnCharge($conge, $this->senior);
        $this->workflow->valider($conge, $this->senior);
        $this->workflow->transmettre($conge, $this->senior);
        $this->workflow->approuver($conge, $this->admin);
    }

    private function solde(int $annee): ?SoldeConge
    {
        return SoldeConge::query()->where(['matricule' => $this->employe->matricule, 'type_conge_id' => $this->annuel->id, 'annee' => $annee])->first();
    }

    // ------------------------------------------------------------- calcul des jours

    public function test_les_jours_calendaires_sont_comptes_inclusivement(): void
    {
        $this->assertSame(5.0, $this->conges->calculerJours($this->annuel, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-06')));
        $this->assertSame(1.0, $this->conges->calculerJours($this->annuel, Carbon::parse('2026-03-06'), Carbon::parse('2026-03-06')));
    }

    public function test_les_jours_ouvres_excluent_le_vendredi(): void
    {
        // Du lundi 2 au dimanche 8 mars 2026 : 7 jours, dont un vendredi.
        $this->assertSame(6.0, $this->conges->calculerJours($this->exceptionnel, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-08')));
    }

    public function test_les_jours_feries_sont_exclus_en_jours_ouvres_seulement(): void
    {
        JourFerie::query()->create(['date_jour' => '2026-03-04', 'libelle' => 'Test']);

        $this->assertSame(5.0, $this->conges->calculerJours($this->exceptionnel, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-08')));
        $this->assertSame(7.0, $this->conges->calculerJours($this->annuel, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-08')));
    }

    public function test_un_jour_ferie_recurrent_vaut_pour_toutes_les_annees(): void
    {
        JourFerie::query()->create(['date_jour' => '2025-03-04', 'libelle' => 'Annuel', 'recurrent' => true]);

        $this->assertSame(5.0, $this->conges->calculerJours($this->exceptionnel, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-08')));
    }

    public function test_un_jour_ferie_inactif_ou_non_recurrent_d_une_autre_annee_est_ignore(): void
    {
        JourFerie::query()->create(['date_jour' => '2026-03-04', 'libelle' => 'Inactif', 'is_active' => false]);
        JourFerie::query()->create(['date_jour' => '2025-03-03', 'libelle' => 'Autre année']);

        $this->assertSame(6.0, $this->conges->calculerJours($this->exceptionnel, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-08')));
    }

    public function test_le_nombre_de_jours_saisi_est_ignore_et_recalcule_par_le_serveur(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-02', '2026-03-06', ['nbre_jours' => 99]);

        $this->assertEquals(5.0, $conge->fresh()->nbre_jours);

        $this->enregistrements->mettreAJour($conge, ['date_fin' => '2026-03-10', 'nbre_jours' => 1], $this->officer);
        $this->assertEquals(9.0, $conge->fresh()->nbre_jours);
    }

    public function test_une_periode_sans_jour_decompte_est_refusee(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('aucun jour décompté');

        // Vendredi 6 mars 2026 seul, en jours ouvrés.
        $this->demande($this->exceptionnel, '2026-03-06', '2026-03-06');
    }

    public function test_une_fin_avant_le_debut_est_refusee(): void
    {
        $this->expectException(RegleMetierException::class);

        $this->demande($this->annuel, '2026-03-10', '2026-03-05');
    }

    // ------------------------------------------------------------- chevauchement

    public function test_un_chevauchement_est_refuse_quel_que_soit_le_type(): void
    {
        $this->demande($this->annuel, '2026-03-02', '2026-03-06');

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('chevauche');

        $this->demande($this->exceptionnel, '2026-03-05', '2026-03-09');
    }

    public function test_les_periodes_contigues_sont_acceptees(): void
    {
        $this->demande($this->annuel, '2026-03-02', '2026-03-06');
        $suivant = $this->demande($this->annuel, '2026-03-07', '2026-03-10');

        $this->assertNotNull($suivant->getKey());
    }

    public function test_un_conge_rejete_ne_bloque_plus_la_periode(): void
    {
        $premier = $this->demande($this->annuel, '2026-03-02', '2026-03-06');
        $this->soumettre($premier);
        $this->workflow->prendreEnCharge($premier, $this->senior);
        $this->workflow->rejeter($premier, $this->senior, 'Période de pointe.');

        $this->assertNotNull($this->demande($this->annuel, '2026-03-02', '2026-03-06')->getKey());
    }

    public function test_modifier_un_brouillon_ne_le_fait_pas_chevaucher_avec_lui_meme(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-02', '2026-03-06');

        $this->enregistrements->mettreAJour($conge, ['date_fin' => '2026-03-07'], $this->officer);

        $this->assertEquals(6.0, $conge->fresh()->nbre_jours);
    }

    // ------------------------------------------------------------- solde

    public function test_le_solde_theorique_est_le_droit_annuel_du_type(): void
    {
        $this->assertSame(30.0, $this->conges->soldeRestant($this->employe->matricule, $this->annuel, 2026));
        $this->assertNull($this->solde(2026), 'Lire un solde ne doit rien écrire.');
    }

    public function test_une_demande_superieure_au_solde_est_refusee(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('Solde insuffisant');

        $this->demande($this->annuel, '2026-03-01', '2026-03-31'); // 31 jours pour 30 de droit
    }

    public function test_les_types_sans_droit_annuel_ne_sont_pas_soumis_au_solde(): void
    {
        $conge = $this->demande($this->exceptionnel, '2026-01-04', '2026-02-28');

        $this->assertGreaterThan(30, (float) $conge->nbre_jours);
        $this->approuver($conge);
        $this->assertSame(0, SoldeConge::query()->count());
    }

    public function test_l_approbation_debite_le_solde_et_pas_avant(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-02', '2026-03-06');
        $this->soumettre($conge);
        $this->assertNull($this->solde(2026));

        $this->workflow->prendreEnCharge($conge, $this->senior);
        $this->workflow->valider($conge, $this->senior);
        $this->workflow->transmettre($conge, $this->senior);
        $this->assertNull($this->solde(2026));

        $this->workflow->approuver($conge, $this->admin);

        $solde = $this->solde(2026);
        $this->assertEquals(30.0, $solde->jours_acquis);
        $this->assertEquals(5.0, $solde->jours_pris);
        $this->assertSame(25.0, $solde->restant());
        $this->assertNotNull($conge->fresh()->reference);
    }

    public function test_un_rejet_ne_debite_rien(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-02', '2026-03-06');
        $this->soumettre($conge);
        $this->workflow->prendreEnCharge($conge, $this->senior);
        $this->workflow->rejeter($conge, $this->senior, 'Refus.');

        $this->assertNull($this->solde(2026));
    }

    public function test_les_jours_en_cours_de_circuit_reduisent_le_disponible(): void
    {
        $premier = $this->demande($this->annuel, '2026-03-01', '2026-03-20'); // 20 jours
        $this->soumettre($premier);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('10 jour(s) disponible(s)');

        $this->demande($this->annuel, '2026-04-01', '2026-04-15'); // 15 jours > 10
    }

    public function test_la_soumission_refuse_un_brouillon_devenu_excessif(): void
    {
        $a = $this->demande($this->annuel, '2026-03-01', '2026-03-20');
        $b = $this->demande($this->annuel, '2026-04-01', '2026-04-20'); // accepté : A n'est qu'un brouillon
        $this->approuver($a);

        try {
            $this->soumettre($b);
            $this->fail('La soumission aurait dû être refusée.');
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString('Solde insuffisant', $e->getMessage());
        }

        $this->assertSame(WorkflowStatut::Brouillon, $b->fresh()->workflow->statut);
    }

    public function test_l_approbation_refuse_si_le_solde_reel_est_insuffisant_et_annule_tout(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-01', '2026-03-20');
        $this->soumettre($conge);
        $this->workflow->prendreEnCharge($conge, $this->senior);
        $this->workflow->valider($conge, $this->senior);
        $this->workflow->transmettre($conge, $this->senior);

        // Entre-temps, le droit de l'agent est ramené à 5 jours.
        SoldeConge::query()->forceCreate(['matricule' => $this->employe->matricule, 'type_conge_id' => $this->annuel->id, 'annee' => 2026, 'jours_acquis' => 5]);

        try {
            $this->workflow->approuver($conge, $this->admin);
            $this->fail('L\'approbation aurait dû être refusée.');
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString('Solde insuffisant', $e->getMessage());
        }

        $conge->refresh();
        $this->assertNull($conge->reference);
        $this->assertSame(WorkflowStatut::EnAttenteApprobation, $conge->workflow->statut);
        $this->assertEquals(0.0, $this->solde(2026)->jours_pris);
    }

    public function test_un_conge_a_cheval_sur_deux_annees_debite_chaque_annee(): void
    {
        $conge = $this->demande($this->annuel, '2026-12-30', '2027-01-02');
        $this->approuver($conge);

        $this->assertEquals(2.0, $this->solde(2026)->jours_pris);
        $this->assertEquals(2.0, $this->solde(2027)->jours_pris);
    }

    public function test_le_report_s_ajoute_au_solde(): void
    {
        SoldeConge::query()->forceCreate(['matricule' => $this->employe->matricule, 'type_conge_id' => $this->annuel->id, 'annee' => 2026, 'jours_acquis' => 30, 'jours_report' => 5]);

        $conge = $this->demande($this->annuel, '2026-03-01', '2026-04-04'); // 35 jours
        $this->approuver($conge);

        $this->assertSame(0.0, $this->solde(2026)->restant());
    }

    public function test_jours_pris_n_est_pas_assignable_en_masse(): void
    {
        $solde = new SoldeConge;
        $solde->fill(['jours_acquis' => 10, 'jours_pris' => 99, 'annee' => 1999, 'matricule' => 'X']);

        $this->assertNull($solde->jours_pris);
        $this->assertNull($solde->annee);
    }

    // ------------------------------------------------------------- droits et interface

    public function test_policy_des_soldes(): void
    {
        $solde = SoldeConge::query()->forceCreate(['matricule' => $this->employe->matricule, 'type_conge_id' => $this->annuel->id, 'annee' => 2026, 'jours_acquis' => 30]);
        $horsPerimetre = $this->utilisateur(RoleName::RhOfficer, [$this->sdA2]);

        $this->assertTrue(Gate::forUser($this->officer)->allows('view', $solde));
        $this->assertFalse(Gate::forUser($this->officer)->allows('update', $solde));
        $this->assertFalse(Gate::forUser($this->senior)->allows('update', $solde));
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $solde));
        $this->assertFalse(Gate::forUser($horsPerimetre)->allows('view', $solde));
        $this->assertFalse(Gate::forUser($this->admin)->allows('create', SoldeConge::class));
        $this->assertFalse(Gate::forUser($this->admin)->allows('delete', $solde));
    }

    public function test_ecrans_jours_feries_et_soldes(): void
    {
        $this->actingAs($this->admin);
        $this->get(JourFerieResource::getUrl('index'))->assertOk();
        $this->get(SoldeCongeResource::getUrl('index'))->assertOk();

        $this->actingAs($this->officer);
        $this->get(JourFerieResource::getUrl('index'))->assertOk(); // lecture pour le personnel RH
        $this->get(SoldeCongeResource::getUrl('index'))->assertOk();
        $this->assertFalse(Gate::forUser($this->officer)->allows('create', JourFerie::class));
    }

    public function test_le_seeder_des_jours_feries_est_idempotent(): void
    {
        $this->seed(\Database\Seeders\JoursFeriesSeeder::class);
        $this->seed(\Database\Seeders\JoursFeriesSeeder::class);

        $this->assertSame(3, JourFerie::query()->count());
    }
}
