<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\ModulesRh\Pages\ListEnregistrementsRh;

class ListDocuments extends ListEnregistrementsRh
{
    protected static string $resource = DocumentResource::class;
}
