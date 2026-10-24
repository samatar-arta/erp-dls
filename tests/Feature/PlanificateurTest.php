<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Enums\RoleName;
use App\Enums\WorkflowStatut;
use App\Models\Employe;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class PlanificateurTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return list<Event> */
    private function taches(string $commande): array
    {
        return array_values(array_filter(
            app(Schedule::class)->events(),
            static fn (Event $e): bool => str_contains($e->command, $commande),
        ));
    }

    public function test_les_deux_commandes_sont_planifiees_sans_chevauchement(): void
    {
        $expiration = $this->taches('dls:expirer-workflows');
        $effets = $this->taches('dls:appliquer-effets');

        $this->assertCount(1, $expiration);
        $this->assertCount(1, $effets);
        $this->assertSame('0 * * * *', $expiration[0]->expression);
        $this->assertSame('5 0 * * *', $effets[0]->expression);
        $this->assertTrue($expiration[0]->withoutOverlapping);
        $this->assertTrue($effets[0]->withoutOverlapping);
        $this->assertSame('Africa/Djibouti', config('app.timezone'));
    }

    public function test_la_commande_expire_les_enregistrements_en_retard(): void
    {
        $this->creerOrganisation();
        $this->seederModules();
        $employe = Employe::factory()->pourSiteDepartement($this->sdA1)->create();
        $officer = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);
        $service = app(WorkflowService::class);

        Carbon::setTestNow('2026-03-02 09:00:00');
        $this->actingAs($officer);
        [$type, $modele] = $this->deuxTypes(ModuleRh::Document);
        $document = $this->creerEnregistrement(ModuleRh::Document, $employe, $type, $modele);
        $service->soumettre($document, $officer);

        $this->artisan('dls:expirer-workflows')->expectsOutput('0 enregistrement(s) expiré(s).')->assertSuccessful();
        $this->assertSame(WorkflowStatut::DocumentSoumis, $document->fresh()->workflow->statut);

        Carbon::setTestNow('2026-03-05 09:00:00');
        $this->artisan('dls:expirer-workflows')->expectsOutput('1 enregistrement(s) expiré(s).')->assertSuccessful();
        $this->assertSame(WorkflowStatut::Expire, $document->fresh()->workflow->statut);
        $this->assertNull($document->fresh()->reference);
    }
}
