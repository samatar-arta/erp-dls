<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types;

use App\Enums\ModuleRh;
use App\Filament\Resources\Rh\TypeRhResource;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class TypeSanctionResource extends TypeRhResource
{
    public static function module(): ModuleRh
    {
        return ModuleRh::Sanction;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            Textarea::make('description')->label('Description')->maxLength(1000)->columnSpanFull(),
            Toggle::make('requires_conseil_discipline')->label('Conseil de discipline requis'),
            Toggle::make('requires_workflow')->label('Passe par le workflow')->default(true),
        ];
    }
}
