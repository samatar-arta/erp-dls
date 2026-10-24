<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles\Pages;

use App\Filament\Resources\Rh\Modeles\ModelCongeResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListModelConges extends ListReferentielRh
{
    protected static string $resource = ModelCongeResource::class;
}
