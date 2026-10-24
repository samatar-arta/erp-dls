<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypeMutationResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListTypeMutations extends ListReferentielRh
{
    protected static string $resource = TypeMutationResource::class;
}
