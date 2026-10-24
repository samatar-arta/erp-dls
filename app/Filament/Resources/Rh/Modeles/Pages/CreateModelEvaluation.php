<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles\Pages;

use App\Filament\Resources\Rh\Modeles\ModelEvaluationResource;
use App\Filament\Resources\Rh\Pages\CreateReferentielRh;

class CreateModelEvaluation extends CreateReferentielRh
{
    protected static string $resource = ModelEvaluationResource::class;
}
