<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles\Pages;

use App\Filament\Resources\Rh\Modeles\ModelDocumentResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListModelDocuments extends ListReferentielRh
{
    protected static string $resource = ModelDocumentResource::class;
}
