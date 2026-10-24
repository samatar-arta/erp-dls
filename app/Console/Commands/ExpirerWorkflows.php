<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WorkflowService;
use Illuminate\Console\Command;

class ExpirerWorkflows extends Command
{
    protected $signature = 'dls:expirer-workflows';

    protected $description = 'Fait expirer les enregistrements dont le délai de traitement est dépassé.';

    public function handle(WorkflowService $workflow): int
    {
        $this->info($workflow->expirerEchus().' enregistrement(s) expiré(s).');

        return self::SUCCESS;
    }
}
