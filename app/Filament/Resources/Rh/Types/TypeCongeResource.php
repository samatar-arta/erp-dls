<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types;

use App\Enums\ModeCalcul;
use App\Enums\ModuleRh;
use App\Filament\Resources\Rh\TypeRhResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;

class TypeCongeResource extends TypeRhResource
{
    public static function module(): ModuleRh
    {
        return ModuleRh::Conge;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            TextInput::make('color')->label('Couleur')->maxLength(20),
            TextInput::make('default_days_per_year')->label('Jours par an (indicatif)')->numeric()->minValue(0),
            Select::make('mode_calcul')->label('Mode de calcul')->options(collect(ModeCalcul::cases())->mapWithKeys(fn (ModeCalcul $m): array => [$m->value => $m->label()])->all())->required()->default('calendaire'),
            Toggle::make('requires_justificatif')->label('Justificatif obligatoire'),
            Toggle::make('requires_workflow')->label('Passe par le workflow')->default(true),
        ];
    }

    protected static function colonnesSpecifiques(): array
    {
        return [
            TextColumn::make('default_days_per_year')->label('Jours/an')->placeholder('—'),
        ];
    }
}
