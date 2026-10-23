<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evaluations;

use App\Enums\ModuleRh;
use App\Filament\Resources\Evaluations\Pages\CreateEvaluation;
use App\Filament\Resources\Evaluations\Pages\EditEvaluation;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Filament\Resources\Evaluations\Pages\ViewEvaluation;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

class EvaluationResource extends ModuleRhResource
{
    protected static ?int $navigationSort = 4;

    public static function module(): ModuleRh
    {
        return ModuleRh::Evaluation;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedClipboardDocumentCheck;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            Select::make('evaluateur_id')->label('Évaluateur')->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->default(fn () => auth()->id())->searchable(),
            DatePicker::make('date_evaluation')->label('Date de l\'évaluation')->required()->default(now()),
            DatePicker::make('periode_debut')->label('Début de période'),
            DatePicker::make('periode_fin')->label('Fin de période')->afterOrEqual('periode_debut'),
            Textarea::make('appreciation')->label('Appréciation')->maxLength(5000)->columnSpanFull(),
            Textarea::make('objectifs')->label('Objectifs')->maxLength(5000)->columnSpanFull(),
        ];
    }

    protected static function affichage(): array
    {
        return [
            'evaluateur.name' => 'Évaluateur',
            'date_evaluation' => 'Date',
            'periode_debut' => 'Début de période',
            'periode_fin' => 'Fin de période',
            'appreciation' => 'Appréciation',
            'objectifs' => 'Objectifs',
        ];
    }
}
