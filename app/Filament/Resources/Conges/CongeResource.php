<?php

declare(strict_types=1);

namespace App\Filament\Resources\Conges;

use App\Enums\ModuleRh;
use App\Filament\Resources\Conges\Pages\CreateConge;
use App\Filament\Resources\Conges\Pages\EditConge;
use App\Filament\Resources\Conges\Pages\ListConges;
use App\Filament\Resources\Conges\Pages\ViewConge;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class CongeResource extends ModuleRhResource
{
    protected static ?int $navigationSort = 2;

    public static function module(): ModuleRh
    {
        return ModuleRh::Conge;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedSun;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            DatePicker::make('date_debut')->label('Début')->required()->live()->afterStateUpdated(fn (Get $get, Set $set) => static::calculerJours($get, $set)),
            DatePicker::make('date_fin')->label('Fin')->required()->afterOrEqual('date_debut')->live()->afterStateUpdated(fn (Get $get, Set $set) => static::calculerJours($get, $set)),
            TextInput::make('nbre_jours')->label('Nombre de jours')->numeric()->required()->minValue(0.5)->step(0.5)->helperText('Calcul provisoire en jours calendaires ; le calcul définitif (jours ouvrés, fériés, solde) arrive en Phase 6.'),
            Textarea::make('motif')->label('Motif')->maxLength(2000)->columnSpanFull(),
        ];
    }

    protected static function affichage(): array
    {
        return [
            'date_debut' => 'Début',
            'date_fin' => 'Fin',
            'nbre_jours' => 'Nombre de jours',
            'motif' => 'Motif',
        ];
    }

    /** Calcul provisoire (jours calendaires inclusifs) ; remplacé par le service de congés en Phase 6. */
    private static function calculerJours(Get $get, Set $set): void
    {
        $debut = $get('date_debut');
        $fin = $get('date_fin');
        if ($debut && $fin && $fin >= $debut) {
            $set('nbre_jours', Carbon::parse($debut)->diffInDays(Carbon::parse($fin)) + 1);
        }
    }
}
