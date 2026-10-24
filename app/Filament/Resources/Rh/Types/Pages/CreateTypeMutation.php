<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypeMutationResource;
use App\Filament\Resources\Rh\Pages\CreateReferentielRh;

class CreateTypeMutation extends CreateReferentielRh
{
    protected static string $resource = TypeMutationResource::class;
}
