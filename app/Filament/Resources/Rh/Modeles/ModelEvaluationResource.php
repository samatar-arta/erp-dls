<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles;

use App\Enums\ModuleRh;
use App\Filament\Resources\Rh\ModeleRhResource;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class ModelEvaluationResource extends ModeleRhResource
{
    public static function module(): ModuleRh
    {
        return ModuleRh::Evaluation;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            Repeater::make('criteres')->label('Critères d\'évaluation')->columnSpanFull()->schema([
                TextInput::make('libelle')->label('Libellé')->required(),
                TextInput::make('coefficient')->label('Coefficient')->numeric()->required()->minValue(0.1),
                TextInput::make('note_max')->label('Note maximale')->numeric()->required()->default(20)->minValue(1),
            ])->columns(3),
        ];
    }
}
