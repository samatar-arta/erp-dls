<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\ModulesRh\Pages\CreateEnregistrementRh;

class CreateDocument extends CreateEnregistrementRh
{
    protected static string $resource = DocumentResource::class;
}
