<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles\Pages;

use App\Filament\Resources\Rh\Modeles\ModelDocumentResource;
use App\Filament\Resources\Rh\Pages\CreateReferentielRh;

class CreateModelDocument extends CreateReferentielRh
{
    protected static string $resource = ModelDocumentResource::class;
}
