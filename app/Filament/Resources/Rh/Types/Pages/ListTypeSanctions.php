<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypeSanctionResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListTypeSanctions extends ListReferentielRh
{
    protected static string $resource = TypeSanctionResource::class;
}
