<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types;

use App\Enums\ModuleRh;
use App\Filament\Resources\Rh\TypeRhResource;
use Filament\Forms\Components\Textarea;

class TypePromotionResource extends TypeRhResource
{
    public static function module(): ModuleRh
    {
        return ModuleRh::Promotion;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            Textarea::make('description')->label('Description')->maxLength(1000)->columnSpanFull(),
        ];
    }
}
