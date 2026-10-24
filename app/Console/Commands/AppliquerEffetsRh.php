<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EffetsRhService;
use Illuminate\Console\Command;

class AppliquerEffetsRh extends Command
{
    protected $signature = 'dls:appliquer-effets';

    protected $description = 'Applique les promotions et mutations approuvées dont la date d\'effet est échue.';

    public function handle(EffetsRhService $effets): int
    {
        $this->info($effets->appliquerEchus().' effet(s) appliqué(s).');

        return self::SUCCESS;
    }
}
