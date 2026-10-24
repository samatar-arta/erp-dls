<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles\Pages;

use App\Filament\Resources\Rh\Modeles\ModelSanctionResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListModelSanctions extends ListReferentielRh
{
    protected static string $resource = ModelSanctionResource::class;
}
