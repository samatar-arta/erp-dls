<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypeSanctionResource;
use App\Filament\Resources\Rh\Pages\CreateReferentielRh;

class CreateTypeSanction extends CreateReferentielRh
{
    protected static string $resource = TypeSanctionResource::class;
}
