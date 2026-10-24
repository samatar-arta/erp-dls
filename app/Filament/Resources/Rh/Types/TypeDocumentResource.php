<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types;

use App\Enums\ModuleRh;
use App\Filament\Resources\Rh\TypeRhResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;

class TypeDocumentResource extends TypeRhResource
{
    public static function module(): ModuleRh
    {
        return ModuleRh::Document;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            Textarea::make('description')->label('Description')->maxLength(1000)->columnSpanFull(),
            Toggle::make('requires_model')->label('Un modèle est obligatoire')->default(true),
            Toggle::make('requires_workflow')->label('Passe par le workflow')->default(true),
            TextInput::make('delai_renouvellement_jours')->label('Délai de renouvellement (jours)')->numeric()->minValue(1)->helperText('Vide : aucun délai.'),
        ];
    }

    protected static function colonnesSpecifiques(): array
    {
        return [
            TextColumn::make('delai_renouvellement_jours')->label('Renouvellement (j)')->placeholder('—'),
        ];
    }
}
