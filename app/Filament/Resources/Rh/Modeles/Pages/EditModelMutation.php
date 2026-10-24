<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles\Pages;

use App\Filament\Resources\Rh\Modeles\ModelMutationResource;
use App\Filament\Resources\Rh\Pages\EditReferentielRh;

class EditModelMutation extends EditReferentielRh
{
    protected static string $resource = ModelMutationResource::class;
}
