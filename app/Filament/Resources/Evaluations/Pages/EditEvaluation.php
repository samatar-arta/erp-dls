<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evaluations\Pages;

use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\ModulesRh\Pages\EditEnregistrementRh;

class EditEvaluation extends EditEnregistrementRh
{
    protected static string $resource = EvaluationResource::class;
}
