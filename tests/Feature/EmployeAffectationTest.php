<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Exceptions\RegleMetierException;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\EmployeAffectation;
use App\Models\SiteDepartement;
use App\Services\EmployeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class EmployeAffectationTest extends TestCase
{
    use CreeOrganisation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(SiteDepartement $sd): array
    {
        return [
            'matricule' => 'M0001', 'name' => 'Ali Hassan', 'fonction' => 'Chauffeur',
            'date_embauche' => '2024-03-01', 'date_naissance' => '1990-05-10',
            'societe_id' => $sd->societe_id, 'site_id' => $sd->site_id, 'departement_id' => $sd->departement_id,
        ];
    }

    public function test_creation_ouvre_la_premiere_affectation(): void
    {
        $user = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);

        $employe = app(EmployeService::class)->creer($this->donnees($this->sdA1), $user);

        $this->assertSame($user->id, $employe->cree_par);
        $this->assertCount(1, $employe->affectations);
        $this->assertNull($employe->affectationCourante->date_fin);
        $this->assertSame($employe->site_id, $employe->affectationCourante->site_id);
    }

    public function test_creation_hors_perimetre_refusee(): void
    {
        $user = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);

        $this->expectException(AuthorizationException::class);
        app(EmployeService::class)->creer($this->donnees($this->sdA2), $user);
    }

    public function test_changement_d_affectation_conserve_l_historique(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $service = app(EmployeService::class);
        $employe = $service->creer($this->donnees($this->sdA1), $admin);

        $service->changerAffectation($employe, [
            'societe_id' => $this->sdA2->societe_id,
            'site_id' => $this->sdA2->site_id,
            'departement_id' => $this->sdA2->departement_id,
            'fonction' => 'Superviseur',
        ], Carbon::parse('2025-01-01'), 'mutation', $admin);

        $employe = $employe->fresh();
        $this->assertSame($this->sdA2->site_id, $employe->site_id);
        $this->assertSame('Superviseur', $employe->fonction);
        $this->assertCount(2, $employe->affectations);
        $this->assertSame(1, $employe->affectations()->whereNull('date_fin')->count());
        $this->assertSame('2025-01-01', $employe->affectations->last()->date_fin->toDateString());
        // La ligne ouverte correspond à la situation courante de l'employé.
        $this->assertSame($employe->site_id, $employe->affectationCourante->site_id);
        $this->assertSame($employe->departement_id, $employe->affectationCourante->departement_id);
    }

    public function test_couple_site_departement_inexistant_refuse(): void
    {
        $admin = $this->utilisateur(RoleName::Admin);
        $employe = app(EmployeService::class)->creer($this->donnees($this->sdA1), $admin);

        $this->expectException(RegleMetierException::class);
        app(EmployeService::class)->changerAffectation($employe, [
            'societe_id' => $this->sdA1->societe_id,
            'site_id' => $this->sdA2->site_id,
            'departement_id' => $this->sdA1->departement_id,
        ], Carbon::parse('2025-01-01'), 'mutation', $admin);
    }

    public function test_modification_directe_de_l_affectation_est_bloquee(): void
    {
        $employe = $this->employeDans($this->sdA1);

        $this->expectException(RegleMetierException::class);
        $employe->update(['site_id' => $this->sdA2->site_id]);
    }

    public function test_une_seule_affectation_ouverte_par_employe_en_base(): void
    {
        $employe = $this->employeDans($this->sdA1);

        $this->expectException(QueryException::class);
        EmployeAffectation::query()->create([
            'employe_id' => $employe->id, 'societe_id' => $this->sdA2->societe_id,
            'site_id' => $this->sdA2->site_id, 'departement_id' => $this->sdA2->departement_id,
            'date_debut' => '2025-01-01',
        ]);
    }

    public function test_departement_d_une_autre_societe_refuse_par_la_base(): void
    {
        $deptB = Departement::query()->where('societe_id', $this->societeB->id)->firstOrFail();

        $this->expectException(QueryException::class);
        SiteDepartement::query()->create([
            'site_id' => $this->sdA1->site_id,
            'societe_id' => $this->societeA->id,
            'departement_id' => $deptB->id,
        ]);
    }

    public function test_employe_avec_departement_d_une_autre_societe_refuse_par_la_base(): void
    {
        $deptB = Departement::query()->where('societe_id', $this->societeB->id)->firstOrFail();

        $this->expectException(QueryException::class);
        Employe::query()->create([
            'matricule' => 'X1', 'name' => 'Test', 'date_embauche' => '2024-01-01',
            'societe_id' => $this->societeA->id, 'site_id' => $this->sdA1->site_id, 'departement_id' => $deptB->id,
        ]);
    }

    public function test_age_et_anciennete_sont_calcules(): void
    {
        Carbon::setTestNow('2026-06-15');
        $employe = Employe::factory()->pourSiteDepartement($this->sdA1)->create([
            'date_naissance' => '1990-06-16', 'date_embauche' => '2024-03-15',
        ]);

        $this->assertSame(35, $employe->age);
        $this->assertSame(27, $employe->anciennete);
        $this->assertArrayNotHasKey('age', $employe->getAttributes());
        Carbon::setTestNow();
    }

    public function test_identifiants_sensibles_masques_a_la_serialisation(): void
    {
        $employe = Employe::factory()->pourSiteDepartement($this->sdA1)->create(['num_CNSS' => '123', 'num_CNI' => '456']);

        $this->assertArrayNotHasKey('num_CNSS', $employe->toArray());
        $this->assertArrayNotHasKey('num_CNI', $employe->toArray());
    }
}
