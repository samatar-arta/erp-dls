<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\QualificationConge;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Conge;
use App\Models\Employe;
use App\Models\SoldeConge;
use App\Models\TypeConge;
use App\Models\User;
use App\Services\EnregistrementRhService;
use App\Services\HistoriqueCongesService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

/** Phase 12 : reprise de l'historique des congés annuels. */
class HistoriqueCongesTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private Employe $employe;

    private User $officer;

    private User $senior;

    private User $admin;

    private TypeConge $annuel;

    private HistoriqueCongesService $historique;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->seederModules();
        $this->employe = $this->employeDans($this->sdA1);
        $this->officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $this->senior = $this->utilisateur(RoleName::RhSenior, [$this->sdA1]);
        $this->admin = $this->utilisateur(RoleName::Admin);
        $this->annuel = TypeConge::query()->where('code', TypeConge::CODE_ANNUEL)->firstOrFail();
        $this->historique = app(HistoriqueCongesService::class);
    }

    private function conge(string $debut, string $fin, array $enPlus = [], ?TypeConge $type = null): Conge
    {
        $type ??= $this->annuel;

        /** @var Conge */
        return app(EnregistrementRhService::class)->creer(ModuleRh::Conge, [
            'matricule' => $this->employe->matricule,
            'type_conge_id' => $type->id,
            'model_conge_id' => $type->modeleParDefaut()->id,
            'date_debut' => $debut,
            'date_fin' => $fin,
        ] + $enPlus, $this->officer);
    }

    private function approuver(Conge $conge): void
    {
        $w = app(WorkflowService::class);
        $w->soumettre($conge, $this->officer);
        $w->prendreEnCharge($conge, $this->senior);
        $w->valider($conge, $this->senior);
        $w->transmettre($conge, $this->senior);
        $w->approuver($conge, $this->admin);
    }

    /** Simule une ligne historique : créée avant les règles DLS, donc sans qualification. */
    private function sansQualification(Conge $conge): Conge
    {
        DB::table('conges')->where('id', $conge->id)->update(['qualification' => null]);

        return $conge->fresh();
    }

    private function jourPris(): ?float
    {
        $valeur = SoldeConge::query()->where(['matricule' => $this->employe->matricule, 'type_conge_id' => $this->annuel->id, 'annee' => 2026])->value('jours_pris');

        return $valeur === null ? null : (float) $valeur;
    }

    public function test_la_simulation_n_ecrit_rien(): void
    {
        $conge = $this->sansQualification($this->conge('2026-03-01', '2026-03-30'));

        $bilan = $this->historique->qualifier(false);

        $this->assertSame(1, $bilan['total']);
        $this->assertSame(1, $bilan['titres']);
        $this->assertNull($conge->fresh()->qualification);
        $this->assertSame(0, AuditLog::query()->where('action', 'conge.historique_qualifie')->count());
    }

    public function test_la_qualification_est_attribuee_selon_la_duree(): void
    {
        $titre = $this->sansQualification($this->conge('2026-03-01', '2026-03-30'));
        $autorisation = $this->sansQualification($this->conge('2026-05-01', '2026-05-10'));

        $bilan = $this->historique->qualifier(true);

        $this->assertSame(2, $bilan['total']);
        $this->assertSame(QualificationConge::TitreConge, $titre->fresh()->qualification);
        $this->assertSame(QualificationConge::AutorisationAbsence, $autorisation->fresh()->qualification);
        $this->assertSame(1, AuditLog::query()->where('action', 'conge.historique_qualifie')->count());
    }

    public function test_un_conge_historique_de_moins_de_5_jours_est_signale_sans_etre_modifie(): void
    {
        $court = $this->sansQualification($this->conge('2026-03-01', '2026-03-10'));
        DB::table('conges')->where('id', $court->id)->update(['date_fin' => '2026-03-03', 'nbre_jours' => 3]);

        $bilan = $this->historique->qualifier(true);

        $this->assertCount(1, $bilan['non_conformes']);
        $this->assertSame(3.0, $bilan['non_conformes'][0]['jours']);
        $court = $court->fresh();
        $this->assertSame(QualificationConge::AutorisationAbsence, $court->qualification);
        $this->assertEquals(3.0, $court->nbre_jours);
        $this->assertSame('2026-03-03', $court->date_fin->toDateString());
    }

    public function test_les_autres_types_et_les_conges_deja_qualifies_sont_ignores(): void
    {
        $maladie = TypeConge::query()->where('code', 'MALADIE')->firstOrFail();
        $autre = $this->conge('2026-03-02', '2026-03-03', [], $maladie);
        $qualifie = $this->conge('2026-05-01', '2026-05-10');

        $bilan = $this->historique->qualifier(true);

        $this->assertSame(0, $bilan['total']);
        $this->assertNull($autre->fresh()->qualification);
        $this->assertSame(QualificationConge::AutorisationAbsence, $qualifie->fresh()->qualification);
    }

    public function test_la_commande_est_idempotente(): void
    {
        $this->sansQualification($this->conge('2026-03-01', '2026-03-30'));

        $this->artisan('dls:qualifier-historique-conges', ['--appliquer' => true])->assertSuccessful();
        $this->assertSame(0, $this->historique->qualifier(true)['total']);
    }

    public function test_la_commande_sans_option_est_une_simulation(): void
    {
        $conge = $this->sansQualification($this->conge('2026-03-01', '2026-03-30'));

        $this->artisan('dls:qualifier-historique-conges', ['--soldes' => true])
            ->expectsOutputToContain('Simulation')
            ->assertSuccessful();

        $this->assertNull($conge->fresh()->qualification);
    }

    public function test_le_recalcul_corrige_un_solde_errone(): void
    {
        $this->approuver($this->conge('2026-03-01', '2026-03-10'));
        $this->assertSame(10.0, $this->jourPris());
        DB::table('soldes_conges')->where('matricule', $this->employe->matricule)->update(['jours_pris' => 25]);

        $simulation = $this->historique->recalculerSoldes(false);
        $this->assertCount(1, $simulation['ecarts']);
        $this->assertSame(25.0, $this->jourPris(), 'La simulation ne corrige rien.');

        $this->historique->recalculerSoldes(true);
        $this->assertSame(10.0, $this->jourPris());
        $this->assertSame(2, AuditLog::query()->where('action', 'solde_conge.modifie')->count(), 'Le débit à l\'approbation et la correction sont tracés.');
    }

    public function test_un_solde_deja_juste_n_est_pas_touche(): void
    {
        $this->approuver($this->conge('2026-03-01', '2026-03-10'));

        $bilan = $this->historique->recalculerSoldes(true);

        $this->assertSame(1, $bilan['verifies']);
        $this->assertSame([], $bilan['ecarts']);
    }

    public function test_une_absence_pendant_les_heures_de_travail_hors_dls_n_est_pas_comptee(): void
    {
        // Agent hors DLS : l'absence en heures de travail n'est jamais déduite ; le solde historique (débité à tort) est ramené à 0.
        $this->approuver($this->conge('2026-03-01', '2026-03-10', ['pendant_heures_travail' => true]));
        $this->assertNull($this->jourPris(), 'Une absence non déduite ne crée pas de solde.');
        DB::table('soldes_conges')->insert([
            'matricule' => $this->employe->matricule, 'type_conge_id' => $this->annuel->id, 'annee' => 2026,
            'jours_acquis' => 30, 'jours_report' => 0, 'jours_pris' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->historique->recalculerSoldes(true);

        $this->assertSame(0.0, $this->jourPris());
    }

    public function test_une_absence_pendant_les_heures_de_travail_d_un_agent_dls_reste_deduite(): void
    {
        $this->societeA->update(['code' => 'DLS']);
        $this->approuver($this->conge('2026-03-01', '2026-03-10', ['pendant_heures_travail' => true]));

        $bilan = $this->historique->recalculerSoldes(true);

        $this->assertSame([], $bilan['ecarts']);
        $this->assertSame(10.0, $this->jourPris());
    }

    public function test_les_conges_non_approuves_ne_comptent_pas(): void
    {
        $this->approuver($this->conge('2026-03-01', '2026-03-10'));
        $this->conge('2026-05-01', '2026-05-10'); // brouillon
        DB::table('soldes_conges')->where('matricule', $this->employe->matricule)->update(['jours_pris' => 20]);

        $this->historique->recalculerSoldes(true);

        $this->assertSame(10.0, $this->jourPris());
    }

    public function test_les_jours_deduits_sans_ligne_de_solde_sont_signales_sans_creation(): void
    {
        $this->approuver($this->conge('2026-03-01', '2026-03-10'));
        DB::table('soldes_conges')->where('matricule', $this->employe->matricule)->delete();

        $bilan = $this->historique->recalculerSoldes(true);

        $this->assertCount(1, $bilan['sans_solde']);
        $this->assertSame(10.0, $bilan['sans_solde'][0]['attendu']);
        $this->assertSame(0, SoldeConge::query()->count());
    }

    public function test_les_ajustements_manuels_acquis_et_report_sont_conserves(): void
    {
        $this->approuver($this->conge('2026-03-01', '2026-03-10'));
        DB::table('soldes_conges')->where('matricule', $this->employe->matricule)->update(['jours_pris' => 3, 'jours_report' => 5]);

        $this->historique->recalculerSoldes(true);

        $solde = SoldeConge::query()->where('matricule', $this->employe->matricule)->firstOrFail();
        $this->assertEquals(10.0, $solde->jours_pris);
        $this->assertEquals(5.0, $solde->jours_report);
    }
}
