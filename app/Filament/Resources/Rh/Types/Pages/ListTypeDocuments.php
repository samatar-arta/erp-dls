<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypeDocumentResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListTypeDocuments extends ListReferentielRh
{
    protected static string $resource = TypeDocumentResource::class;
}
