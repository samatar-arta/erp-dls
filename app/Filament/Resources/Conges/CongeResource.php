<?php

declare(strict_types=1);

namespace App\Filament\Resources\Conges;

use App\Enums\ModuleRh;
use App\Enums\MotifPermission;
use App\Filament\Resources\Conges\Pages\CreateConge;
use App\Filament\Resources\Conges\Pages\EditConge;
use App\Filament\Resources\Conges\Pages\ListConges;
use App\Filament\Resources\Conges\Pages\ViewConge;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use App\Models\TypeConge;
use App\Services\CongeService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
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
            Toggle::make('demi_debut')->label('Départ l\'après-midi (demi-journée le premier jour)')->live()->afterStateUpdated(fn (Get $get, Set $set) => static::calculerJours($get, $set)),
            Toggle::make('demi_fin')->label('Retour l\'après-midi (demi-journée le dernier jour)')->live()->afterStateUpdated(fn (Get $get, Set $set) => static::calculerJours($get, $set)),
            Toggle::make('pendant_heures_travail')->label('Absence durant les heures de travail')
                ->helperText('Autorisation d\'absence (5 à 29 jours) : déduite du solde pour les agents DLS uniquement, hors agents mis à disposition d\'un client.')
                ->visible(fn (Get $get): bool => static::typeChoisi($get)?->estAnnuel() === true),
            Select::make('motif_permission')->label('Motif de la permission')
                ->options(MotifPermission::options())
                ->required(fn (Get $get): bool => static::typeChoisi($get)?->estPermissionExceptionnelle() === true)
                ->visible(fn (Get $get): bool => static::typeChoisi($get)?->estPermissionExceptionnelle() === true)
                ->helperText('Crédit de 11 jours ouvrés par année civile, sans effet sur le solde de congé annuel.'),
            TextInput::make('nbre_jours')->label('Nombre de jours')->readOnly()->dehydrated(false)
                ->helperText('Calculé selon le type (jours calendaires ou ouvrés, jours fériés exclus). Le solde et les chevauchements sont contrôlés à l\'enregistrement.'),
            Textarea::make('motif')->label('Motif')->maxLength(2000)->columnSpanFull(),
        ];
    }

    protected static function affichage(): array
    {
        return [
            'date_debut' => 'Début',
            'date_fin' => 'Fin',
            'demi_debut' => 'Demi-journée au début',
            'demi_fin' => 'Demi-journée à la fin',
            'nbre_jours' => 'Nombre de jours',
            'qualification_libelle' => 'Qualification',
            'motif_permission_libelle' => 'Motif de la permission',
            'pendant_heures_travail' => 'Durant les heures de travail',
            'motif' => 'Motif',
        ];
    }

    private static function typeChoisi(Get $get): ?TypeConge
    {
        return $get('type_conge_id') === null ? null : TypeConge::query()->find($get('type_conge_id'));
    }

    /** Aperçu du calcul ; le nombre de jours est de toute façon recalculé par le service à l'enregistrement. */
    private static function calculerJours(Get $get, Set $set): void
    {
        $debut = $get('date_debut');
        $fin = $get('date_fin');
        $type = TypeConge::query()->find($get('type_conge_id'));

        if (! $debut || ! $fin || $type === null || $fin < $debut) {
            return;
        }

        $set('nbre_jours', app(CongeService::class)->calculerJours($type, Carbon::parse($debut), Carbon::parse($fin), (bool) $get('demi_debut'), (bool) $get('demi_fin')));
    }
}
