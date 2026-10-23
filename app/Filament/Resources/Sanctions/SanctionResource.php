<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sanctions;

use App\Enums\ModuleRh;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use App\Filament\Resources\Sanctions\Pages\CreateSanction;
use App\Filament\Resources\Sanctions\Pages\EditSanction;
use App\Filament\Resources\Sanctions\Pages\ListSanctions;
use App\Filament\Resources\Sanctions\Pages\ViewSanction;
use App\Models\NiveauSanction;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

class SanctionResource extends ModuleRhResource
{
    protected static ?int $navigationSort = 3;

    public static function module(): ModuleRh
    {
        return ModuleRh::Sanction;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedExclamationTriangle;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            Select::make('niveau_sanction_id')->label('Niveau')->options(fn (): array => NiveauSanction::query()->orderBy('id')->pluck('name', 'id')->all())->required(),
            DatePicker::make('date_sanction')->label('Date de la sanction')->required(),
            TextInput::make('motif')->label('Motif')->maxLength(255),
            Textarea::make('description')->label('Faits reprochés')->required()->maxLength(5000)->columnSpanFull(),
        ];
    }

    protected static function affichage(): array
    {
        return [
            'niveau.name' => 'Niveau',
            'date_sanction' => 'Date',
            'motif' => 'Motif',
            'description' => 'Faits reprochés',
        ];
    }
}
