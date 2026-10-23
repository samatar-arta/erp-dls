<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sanctions\Pages;

use App\Filament\Resources\Sanctions\SanctionResource;
use App\Filament\Resources\ModulesRh\Pages\EditEnregistrementRh;

class EditSanction extends EditEnregistrementRh
{
    protected static string $resource = SanctionResource::class;
}
