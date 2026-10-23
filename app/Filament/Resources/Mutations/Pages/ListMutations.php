<?php

declare(strict_types=1);

namespace App\Filament\Resources\Mutations\Pages;

use App\Filament\Resources\Mutations\MutationResource;
use App\Filament\Resources\ModulesRh\Pages\ListEnregistrementsRh;

class ListMutations extends ListEnregistrementsRh
{
    protected static string $resource = MutationResource::class;
}
