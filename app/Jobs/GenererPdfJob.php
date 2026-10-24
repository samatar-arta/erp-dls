<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ModuleRh;
use App\Services\GenerationPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Génération asynchrone du PDF. Idempotent (voir GenerationPdfService) : rejouer le job est sans effet
 * si le PDF existe déjà, et ne change jamais la référence.
 */
class GenererPdfJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly string $module, public readonly int $id)
    {
    }

    public function uniqueId(): string
    {
        return $this->module.':'.$this->id;
    }

    public function handle(GenerationPdfService $service): void
    {
        $module = ModuleRh::from($this->module);
        $enregistrement = $module->enregistrementClass()::query()->find($this->id);

        if ($enregistrement !== null) {
            $service->generer($enregistrement);
        }
    }
}
