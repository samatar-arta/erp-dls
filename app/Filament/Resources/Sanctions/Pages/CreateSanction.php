<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sanctions\Pages;

use App\Filament\Resources\Sanctions\SanctionResource;
use App\Filament\Resources\ModulesRh\Pages\CreateEnregistrementRh;

class CreateSanction extends CreateEnregistrementRh
{
    protected static string $resource = SanctionResource::class;
}
