<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Exceptions\RegleMetierException;
use App\Services\ReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreeModulesRh;
use Tests\TestCase;

class ReferencesTest extends TestCase
{
    use CreeModulesRh, RefreshDatabase;

    private ReferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferenceService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('modules')]
    public function test_format_par_defaut_de_chaque_module(ModuleRh $module): void
    {
        $this->assertSame(
            '0001/'.$module->prefixeParDefaut().'/'.now()->year,
            $this->service->attribuer($module),
        );
    }

    public function test_prefixes_attendus(): void
    {
        $attendus = [
            'DOCUMENT' => 'DA_DARH/DLS', 'SANCTION' => 'DS_DARH/DLS', 'EVALUATION' => 'EV_DARH/DLS',
            'PROMOTION' => 'PR_DARH/DLS', 'MUTATION' => 'MU_DARH/DLS', 'CONGE' => 'CG_DARH/DLS',
        ];

        foreach (ModuleRh::cases() as $module) {
            $this->assertSame($attendus[$module->value], $module->prefixeParDefaut());
        }
    }

    public function test_la_numerotation_est_sequentielle_sans_trou(): void
    {
        $this->assertSame('0001/DA_DARH/DLS/'.now()->year, $this->service->attribuer(ModuleRh::Document));
        $this->assertSame('0002/DA_DARH/DLS/'.now()->year, $this->service->attribuer(ModuleRh::Document));
        $this->assertSame('0003/DA_DARH/DLS/'.now()->year, $this->service->attribuer(ModuleRh::Document));
    }

    public function test_chaque_module_a_son_propre_compteur(): void
    {
        $this->service->attribuer(ModuleRh::Document);
        $this->service->attribuer(ModuleRh::Document);

        $this->assertStringStartsWith('0001/CG_DARH/DLS/', $this->service->attribuer(ModuleRh::Conge));
        $this->assertStringStartsWith('0003/DA_DARH/DLS/', $this->service->attribuer(ModuleRh::Document));
    }

    public function test_le_compteur_repart_de_un_chaque_annee(): void
    {
        Carbon::setTestNow('2026-12-31 23:00:00');
        $this->assertSame('0001/DA_DARH/DLS/2026', $this->service->attribuer(ModuleRh::Document));
        $this->assertSame('0002/DA_DARH/DLS/2026', $this->service->attribuer(ModuleRh::Document));

        Carbon::setTestNow('2027-01-01 08:00:00');
        $this->assertSame('0001/DA_DARH/DLS/2027', $this->service->attribuer(ModuleRh::Document));

        // L'ancienne année garde sa suite.
        $this->assertSame('0003/DA_DARH/DLS/2026', $this->service->attribuer(ModuleRh::Document, Carbon::parse('2026-06-01')));
    }

    public function test_une_seule_sequence_par_module_et_par_annee(): void
    {
        $this->service->attribuer(ModuleRh::Document);
        $this->service->attribuer(ModuleRh::Document);

        $this->assertSame(1, DB::table('reference_sequences')->where('module', 'DOCUMENT')->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('reference_sequences')->insert([
            'module' => 'DOCUMENT', 'annee' => now()->year, 'dernier_numero' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_un_numero_annule_est_rendu_pas_de_trou(): void
    {
        try {
            DB::transaction(function (): void {
                $this->service->attribuer(ModuleRh::Document);
                throw new \RuntimeException('annulation');
            });
        } catch (\RuntimeException) {
        }

        $this->assertStringStartsWith('0001/', $this->service->attribuer(ModuleRh::Document));
    }

    public function test_prefixe_et_format_configurables_sans_toucher_aux_references_existantes(): void
    {
        $premiere = $this->service->attribuer(ModuleRh::Document);

        $this->service->configurer(ModuleRh::Document, 'DOC_RH/DLS', '{prefixe}-{numero:5}-{annee}');

        $this->assertSame('DOC_RH/DLS-00002-'.now()->year, $this->service->attribuer(ModuleRh::Document));
        $this->assertSame('0001/DA_DARH/DLS/'.now()->year, $premiere);
    }

    public function test_configuration_invalide_refusee(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->service->configurer(ModuleRh::Document, 'X', '{prefixe}/{annee}');
    }

    public function test_prefixe_invalide_refuse(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->service->configurer(ModuleRh::Document, 'a b; DROP');
    }

    public function test_pas_de_tables_hors_perimetre(): void
    {
        foreach (['paies', 'paylists', 'contacts'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    /**
     * Vraie concurrence : plusieurs processus PHP distincts, chacun sa connexion et sa transaction.
     * Les numéros doivent être tous distincts et consécutifs.
     */
    public function test_concurrence_reelle_entre_processus(): void
    {
        $nombre = 6;
        $script = base_path('tests/Support/attribuer_reference.php');
        $env = array_merge(getenv(), [
            'DB_DATABASE' => (string) config('database.connections.'.config('database.default').'.database'),
            'APP_ENV' => 'testing',
        ]);

        // Les enfants valident leurs écritures hors de la transaction du test (rollback à la fin) :
        // nettoyage avant et après par un processus dédié, jamais dans la transaction du test
        // (ses verrous bloqueraient les enfants).
        $this->purgerSequences($script, $env);

        try {
            $processus = [];
            for ($i = 0; $i < $nombre; $i++) {
                $process = proc_open(
                    [PHP_BINARY, $script, 'EVALUATION'],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    base_path(),
                    $env,
                );
                $this->assertIsResource($process);
                $processus[] = [$process, $pipes];
            }

            $references = [];
            foreach ($processus as [$process, $pipes]) {
                $sortie = trim((string) stream_get_contents($pipes[1]));
                $erreur = trim((string) stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), "Processus enfant en échec : {$erreur}");
                $references[] = $sortie;
            }

            $this->assertCount($nombre, array_unique($references), 'Doublon de référence : '.implode(', ', $references));

            $numeros = array_map(static fn (string $r): int => (int) explode('/', $r)[0], $references);
            sort($numeros);
            $this->assertSame(range(1, $nombre), $numeros);
        } finally {
            $this->purgerSequences($script, $env);
        }
    }

    /**
     * @param  array<string, string|false>  $env
     */
    private function purgerSequences(string $script, array $env): void
    {
        $process = proc_open([PHP_BINARY, $script, 'EVALUATION', 'purge'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
        $this->assertIsResource($process);
        stream_get_contents($pipes[1]);
        $erreur = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), "Purge en échec : {$erreur}");
    }
}
