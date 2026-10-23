<?php

declare(strict_types=1);

namespace App\Filament\Resources\Conges\Pages;

use App\Filament\Resources\Conges\CongeResource;
use App\Filament\Resources\ModulesRh\Pages\ListEnregistrementsRh;

class ListConges extends ListEnregistrementsRh
{
    protected static string $resource = CongeResource::class;
}
