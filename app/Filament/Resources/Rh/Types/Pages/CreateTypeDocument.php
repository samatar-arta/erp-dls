<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypeDocumentResource;
use App\Filament\Resources\Rh\Pages\CreateReferentielRh;

class CreateTypeDocument extends CreateReferentielRh
{
    protected static string $resource = TypeDocumentResource::class;
}
