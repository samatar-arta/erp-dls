<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles\Pages;

use App\Filament\Resources\Rh\Modeles\ModelMutationResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListModelMutations extends ListReferentielRh
{
    protected static string $resource = ModelMutationResource::class;
}
