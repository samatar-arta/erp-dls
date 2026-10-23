<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evaluations\Pages;

use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\ModulesRh\Pages\CreateEnregistrementRh;

class CreateEvaluation extends CreateEnregistrementRh
{
    protected static string $resource = EvaluationResource::class;
}
