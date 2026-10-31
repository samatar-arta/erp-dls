<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\MotifPermission;
use App\Enums\QualificationConge;
use App\Enums\RoleName;
use App\Exceptions\RegleMetierException;
use App\Models\Conge;
use App\Models\Employe;
use App\Models\SoldeConge;
use App\Models\Societe;
use App\Models\TypeConge;
use App\Models\User;
use App\Services\CongeService;
use App\Services\EnregistrementRhService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

/** Règles DLS : qualification titre de congé / autorisation d'absence, déduction du solde, permissions exceptionnelles. */
class CongesReglesDlsTest extends TestCase
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

    private TypeConge $permission;

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
        $this->annuel = TypeConge::query()->where('code', TypeConge::CODE_ANNUEL)->firstOrFail();
        $this->permission = TypeConge::query()->where('code', TypeConge::CODE_PERMISSION)->firstOrFail();
    }

    private function demande(TypeConge $type, string $debut, string $fin, array $enPlus = [], ?Employe $employe = null): Conge
    {
        /** @var Conge */
        return $this->enregistrements->creer(ModuleRh::Conge, [
            'matricule' => ($employe ?? $this->employe)->matricule,
            'type_conge_id' => $type->id,
            'model_conge_id' => $type->modeleParDefaut()->id,
            'date_debut' => $debut,
            'date_fin' => $fin,
        ] + $enPlus, $this->officer);
    }

    private function permission(string $debut, string $fin, MotifPermission $motif): Conge
    {
        return $this->demande($this->permission, $debut, $fin, ['motif_permission' => $motif->value]);
    }

    private function approuver(Conge $conge): void
    {
        $this->workflow->soumettre($conge, $this->officer);
        $this->workflow->prendreEnCharge($conge, $this->senior);
        $this->workflow->valider($conge, $this->senior);
        $this->workflow->transmettre($conge, $this->senior);
        $this->workflow->approuver($conge, $this->admin);
    }

    private function joursPris(): float
    {
        return (float) (SoldeConge::query()->where(['matricule' => $this->employe->matricule, 'type_conge_id' => $this->annuel->id, 'annee' => 2026])->value('jours_pris') ?? 0);
    }

    /** Rattache l'agent de test à la société DLS (le département appartient à cette société). */
    private function faireDeDls(): void
    {
        $this->societeA->update(['code' => 'DLS']);
    }

    // ------------------------------------------------- 1 et 2 : titre de congé / autorisation d'absence

    public function test_30_jours_calendaires_ou_plus_sont_un_titre_de_conge(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-01', '2026-03-30'); // 30 jours

        $this->assertSame(QualificationConge::TitreConge, $conge->fresh()->qualification);
        $this->assertSame(30.0, (float) $conge->nbre_jours);
    }

    public function test_de_5_a_29_jours_sont_une_autorisation_d_absence(): void
    {
        $this->assertSame(QualificationConge::AutorisationAbsence, $this->demande($this->annuel, '2026-03-01', '2026-03-05')->fresh()->qualification); // 5 j
        $this->assertSame(QualificationConge::AutorisationAbsence, $this->demande($this->annuel, '2026-04-01', '2026-04-29')->fresh()->qualification); // 29 j
    }

    public function test_une_autorisation_d_absence_de_moins_de_5_jours_est_refusee(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('au moins 5 jours');

        $this->demande($this->annuel, '2026-03-01', '2026-03-04'); // 4 jours
    }

    public function test_les_demi_journees_comptent_dans_le_seuil_de_5_jours(): void
    {
        $this->expectException(RegleMetierException::class);

        $this->demande($this->annuel, '2026-03-01', '2026-03-05', ['demi_debut' => true]); // 4,5 jours
    }

    public function test_les_seuils_se_calculent_en_jours_calendaires_meme_avec_week_ends(): void
    {
        // 5 jours calendaires (dimanche à jeudi) restent valides ; le seuil n'est pas évalué en jours ouvrés.
        $this->assertSame(5.0, $this->conges->joursCalendaires(Carbon::parse('2026-03-05'), Carbon::parse('2026-03-09')));
        $this->assertNull($this->conges->qualifier($this->permission, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-02')));
    }

    public function test_les_seuils_ne_s_appliquent_qu_au_type_annuel(): void
    {
        $maladie = TypeConge::query()->where('code', 'MALADIE')->firstOrFail();

        $conge = $this->demande($maladie, '2026-03-02', '2026-03-03'); // 2 jours : pas de seuil

        $this->assertNull($conge->fresh()->qualification);
    }

    public function test_modifier_un_brouillon_sous_le_seuil_est_refuse(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-01', '2026-03-10');

        $this->expectException(RegleMetierException::class);

        $this->enregistrements->mettreAJour($conge, ['date_fin' => '2026-03-03'], $this->officer);
    }

    public function test_la_qualification_est_recalculee_a_la_modification(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-01', '2026-03-10');
        $this->enregistrements->mettreAJour($conge, ['date_fin' => '2026-03-30'], $this->officer);

        $this->assertSame(QualificationConge::TitreConge, $conge->fresh()->qualification);
    }

    public function test_la_qualification_saisie_est_ignoree(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-01', '2026-03-10', ['qualification' => 'TITRE_CONGE']);

        $this->assertSame(QualificationConge::AutorisationAbsence, $conge->fresh()->qualification);
    }

    // ------------------------------------------------- 3 et 4 : déduction du solde

    public function test_une_autorisation_d_absence_est_deduite_du_solde_par_defaut(): void
    {
        $this->approuver($this->demande($this->annuel, '2026-03-01', '2026-03-10'));

        $this->assertSame(10.0, $this->joursPris());
    }

    public function test_un_titre_de_conge_est_deduit_du_solde(): void
    {
        $this->approuver($this->demande($this->annuel, '2026-03-01', '2026-03-30'));

        $this->assertSame(30.0, $this->joursPris());
    }

    public function test_heures_de_travail_agent_dls_la_deduction_s_applique(): void
    {
        $this->faireDeDls();

        $this->approuver($this->demande($this->annuel, '2026-03-01', '2026-03-10', ['pendant_heures_travail' => true]));

        $this->assertSame(10.0, $this->joursPris());
    }

    public function test_heures_de_travail_agent_hors_dls_aucune_deduction(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-01', '2026-03-10', ['pendant_heures_travail' => true]);
        $this->approuver($conge);

        $this->assertTrue($conge->fresh()->pendant_heures_travail);
        $this->assertSame(0.0, $this->joursPris());
    }

    public function test_heures_de_travail_agent_dls_mis_a_disposition_aucune_deduction(): void
    {
        $this->faireDeDls();
        $sgtd = Societe::query()->create(['name' => 'SGTD', 'code' => 'SGTD']);
        $this->employe->update(['mis_a_disposition_societe_id' => $sgtd->id]);

        $this->approuver($this->demande($this->annuel, '2026-03-01', '2026-03-10', ['pendant_heures_travail' => true]));

        $this->assertSame(0.0, $this->joursPris());
    }

    public function test_hors_heures_de_travail_un_agent_mis_a_disposition_est_quand_meme_deduit(): void
    {
        $this->faireDeDls();
        $sgtd = Societe::query()->create(['name' => 'SGTD', 'code' => 'SGTD']);
        $this->employe->update(['mis_a_disposition_societe_id' => $sgtd->id]);

        $this->approuver($this->demande($this->annuel, '2026-03-01', '2026-03-10'));

        $this->assertSame(10.0, $this->joursPris());
    }

    public function test_une_absence_non_deduite_ne_reduit_pas_le_disponible(): void
    {
        // Agent hors DLS : 25 jours engagés pendant les heures de travail ne consomment pas le solde de 30.
        $this->workflow->soumettre($this->demande($this->annuel, '2026-03-01', '2026-03-25', ['pendant_heures_travail' => true]), $this->officer);
        $deuxieme = $this->demande($this->annuel, '2026-04-01', '2026-04-29');
        $this->workflow->soumettre($deuxieme, $this->officer);

        $this->assertSame(29.0, (float) $deuxieme->nbre_jours);
    }

    public function test_une_absence_non_deduite_n_est_pas_limitee_par_le_solde(): void
    {
        $this->approuver($this->demande($this->annuel, '2026-03-01', '2026-03-30')); // solde épuisé
        $conge = $this->demande($this->annuel, '2026-05-01', '2026-05-29', ['pendant_heures_travail' => true]);
        $this->approuver($conge);

        $this->assertSame(30.0, $this->joursPris());
    }

    public function test_une_absence_deduite_est_limitee_par_le_solde(): void
    {
        $this->approuver($this->demande($this->annuel, '2026-03-01', '2026-03-30'));

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('Solde insuffisant');

        $this->demande($this->annuel, '2026-05-01', '2026-05-29');
    }

    public function test_l_indicateur_heures_de_travail_est_ecarte_pour_un_titre_de_conge(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-01', '2026-03-30', ['pendant_heures_travail' => true]);

        $this->assertFalse($conge->fresh()->pendant_heures_travail);
    }

    // ------------------------------------------------- 5 : permissions exceptionnelles

    public function test_le_bareme_des_motifs(): void
    {
        $this->assertSame(3.0, MotifPermission::MariageTravailleur->jours());
        $this->assertSame(1.0, MotifPermission::MariageEnfant->jours());
        $this->assertSame(3.0, MotifPermission::DecesProche->jours());
        $this->assertSame(1.0, MotifPermission::DecesFratrie->jours());
        $this->assertSame(1.0, MotifPermission::DecesBeauParent->jours());
        $this->assertSame(3.0, MotifPermission::Naissance->jours());
        $this->assertSame(11.0, MotifPermission::PLAFOND_ANNUEL);
    }

    public function test_une_permission_ne_reduit_pas_le_solde_de_conge_annuel(): void
    {
        $this->approuver($this->permission('2026-03-02', '2026-03-04', MotifPermission::MariageTravailleur));

        $this->assertSame(0.0, $this->joursPris());
        $this->assertSame(30.0, $this->conges->soldeRestant($this->employe->matricule, $this->annuel, 2026));
        $this->assertNull(SoldeConge::query()->where('matricule', $this->employe->matricule)->first());
    }

    public function test_une_permission_est_exemptee_du_seuil_de_5_jours(): void
    {
        $conge = $this->permission('2026-03-02', '2026-03-02', MotifPermission::MariageEnfant);

        $this->assertSame(1.0, (float) $conge->nbre_jours);
        $this->assertNull($conge->fresh()->qualification);
        $this->assertSame(MotifPermission::MariageEnfant, $conge->fresh()->motif_permission);
    }

    public function test_le_motif_est_obligatoire(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('motif');

        $this->demande($this->permission, '2026-03-02', '2026-03-02');
    }

    public function test_un_motif_inconnu_est_refuse(): void
    {
        $this->expectException(RegleMetierException::class);

        $this->demande($this->permission, '2026-03-02', '2026-03-02', ['motif_permission' => 'VACANCES']);
    }

    public function test_la_duree_ne_peut_pas_depasser_le_bareme_du_motif(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('1 jour');

        $this->permission('2026-03-02', '2026-03-03', MotifPermission::DecesFratrie); // 2 jours ouvrés pour un barème de 1
    }

    public function test_les_permissions_se_comptent_en_jours_ouvres(): void
    {
        // Du jeudi 5 au dimanche 8 mars : vendredi et samedi exclus, soit 2 jours ouvrés (jeudi, dimanche) <= 3.
        $conge = $this->permission('2026-03-05', '2026-03-08', MotifPermission::Naissance);

        $this->assertSame(2.0, (float) $conge->nbre_jours);
    }

    public function test_le_motif_est_ignore_pour_un_autre_type(): void
    {
        $conge = $this->demande($this->annuel, '2026-03-01', '2026-03-10', ['motif_permission' => MotifPermission::Naissance->value]);

        $this->assertNull($conge->fresh()->motif_permission);
    }

    public function test_le_plafond_de_11_jours_par_annee_est_applique(): void
    {
        // Les permissions en circuit comptent dans le crédit (3 + 3 + 3 + 1 + 1 = 11).
        foreach ([
            ['2026-03-02', '2026-03-04', MotifPermission::Naissance],
            ['2026-03-09', '2026-03-11', MotifPermission::MariageTravailleur],
            ['2026-03-16', '2026-03-18', MotifPermission::DecesProche],
            ['2026-03-23', '2026-03-23', MotifPermission::DecesFratrie],
            ['2026-03-24', '2026-03-24', MotifPermission::MariageEnfant],
        ] as [$debut, $fin, $motif]) {
            $this->workflow->soumettre($this->permission($debut, $fin, $motif), $this->officer);
        }

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('Crédit de permissions exceptionnelles dépassé');

        $this->permission('2026-03-25', '2026-03-25', MotifPermission::DecesBeauParent); // 12
    }

    public function test_le_credit_se_renouvelle_l_annee_suivante(): void
    {
        foreach ([['2026-03-02', '2026-03-04'], ['2026-03-09', '2026-03-11'], ['2026-03-16', '2026-03-18']] as [$debut, $fin]) {
            $this->workflow->soumettre($this->permission($debut, $fin, MotifPermission::Naissance), $this->officer);
        }
        $this->workflow->soumettre($this->permission('2026-03-23', '2026-03-23', MotifPermission::MariageEnfant), $this->officer);
        $this->workflow->soumettre($this->permission('2026-03-24', '2026-03-24', MotifPermission::MariageEnfant), $this->officer);

        $conge = $this->permission('2027-03-01', '2027-03-03', MotifPermission::Naissance);

        $this->assertSame(3.0, (float) $conge->nbre_jours);
    }

    public function test_le_plafond_est_individuel(): void
    {
        foreach ([['2026-03-02', '2026-03-04'], ['2026-03-09', '2026-03-11'], ['2026-03-16', '2026-03-18']] as [$debut, $fin]) {
            $this->workflow->soumettre($this->permission($debut, $fin, MotifPermission::Naissance), $this->officer);
        }
        $autre = $this->employeDans($this->sdA1);

        $conge = $this->demande($this->permission, '2026-03-02', '2026-03-04', ['motif_permission' => MotifPermission::Naissance->value], $autre);

        $this->assertSame(3.0, (float) $conge->nbre_jours);
    }

    public function test_une_permission_rejetee_ne_consomme_pas_le_credit(): void
    {
        $rejetee = $this->permission('2026-03-02', '2026-03-04', MotifPermission::Naissance);
        $this->workflow->soumettre($rejetee, $this->officer);
        $this->workflow->prendreEnCharge($rejetee, $this->senior);
        $this->workflow->rejeter($rejetee, $this->senior, 'Justificatif manquant');

        foreach ([['2026-03-09', '2026-03-11'], ['2026-03-16', '2026-03-18'], ['2026-03-23', '2026-03-25']] as [$debut, $fin]) {
            $this->workflow->soumettre($this->permission($debut, $fin, MotifPermission::Naissance), $this->officer);
        }
        $conge = $this->permission('2026-03-30', '2026-03-31', MotifPermission::Naissance); // 9 + 2 = 11

        $this->assertSame(2.0, (float) $conge->nbre_jours);
    }

    public function test_la_soumission_refuse_un_depassement_du_plafond(): void
    {
        // Les brouillons ne consomment pas le crédit : quatre brouillons de 3 jours peuvent exister.
        $brouillons = [];
        foreach ([['2026-03-02', '2026-03-04'], ['2026-03-09', '2026-03-11'], ['2026-03-16', '2026-03-18'], ['2026-03-23', '2026-03-25']] as [$debut, $fin]) {
            $brouillons[] = $this->permission($debut, $fin, MotifPermission::Naissance);
        }
        foreach (array_slice($brouillons, 0, 3) as $brouillon) {
            $this->approuver($brouillon);
        }

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('Crédit de permissions exceptionnelles dépassé');

        $this->workflow->soumettre($brouillons[3], $this->officer);
    }

    public function test_l_approbation_refuse_un_depassement_du_plafond(): void
    {
        $brouillons = [];
        foreach ([['2026-03-02', '2026-03-04'], ['2026-03-09', '2026-03-11'], ['2026-03-16', '2026-03-18'], ['2026-03-23', '2026-03-25']] as [$debut, $fin]) {
            $brouillons[] = $this->permission($debut, $fin, MotifPermission::Naissance);
        }
        foreach (array_slice($brouillons, 0, 3) as $brouillon) {
            $this->approuver($brouillon);
        }

        $this->expectException(RegleMetierException::class);

        $this->conges->debiterALApprobation($brouillons[3]->fresh());
    }

    public function test_onze_jours_exactement_sont_acceptes_a_l_approbation(): void
    {
        foreach ([['2026-03-02', '2026-03-04'], ['2026-03-09', '2026-03-11'], ['2026-03-16', '2026-03-18'], ['2026-03-23', '2026-03-24']] as [$debut, $fin]) {
            $this->approuver($this->permission($debut, $fin, MotifPermission::Naissance));
        }

        $this->assertSame(11.0, (float) Conge::query()->where('matricule', $this->employe->matricule)->sum('nbre_jours'));
        $this->assertSame(0.0, $this->joursPris());
    }
}
