<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypeMutationResource;
use App\Filament\Resources\Rh\Pages\EditReferentielRh;

class EditTypeMutation extends EditReferentielRh
{
    protected static string $resource = TypeMutationResource::class;
}
