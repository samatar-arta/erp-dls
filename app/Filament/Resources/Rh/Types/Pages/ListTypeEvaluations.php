<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypeEvaluationResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListTypeEvaluations extends ListReferentielRh
{
    protected static string $resource = TypeEvaluationResource::class;
}
