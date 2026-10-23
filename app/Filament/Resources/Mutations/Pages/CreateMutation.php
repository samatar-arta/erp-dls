<?php

declare(strict_types=1);

namespace App\Filament\Resources\Mutations\Pages;

use App\Filament\Resources\Mutations\MutationResource;
use App\Filament\Resources\ModulesRh\Pages\CreateEnregistrementRh;

class CreateMutation extends CreateEnregistrementRh
{
    protected static string $resource = MutationResource::class;
}
