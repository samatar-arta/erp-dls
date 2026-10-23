<?php

// Processus enfant du test de concurrence de ReferencesTest.
// Usage : php attribuer_reference.php MODULE [purge]
// Les enfants valident leurs écritures hors de la transaction du test : « purge » nettoie ensuite.

declare(strict_types=1);

use App\Enums\ModuleRh;
use App\Services\ReferenceService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! str_ends_with((string) config('database.connections.'.config('database.default').'.database'), '_test')) {
    fwrite(STDERR, "Base non test\n");
    exit(2);
}

try {
    $module = ModuleRh::from($argv[1]);

    if (($argv[2] ?? null) === 'purge') {
        DB::table('reference_sequences')->where('module', $module->value)->delete();

        exit(0);
    }

    echo app(ReferenceService::class)->attribuer($module);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n");
    exit(1);
}
