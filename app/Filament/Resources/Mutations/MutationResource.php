<?php

declare(strict_types=1);

namespace App\Filament\Resources\Mutations;

use App\Enums\ModuleRh;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use App\Filament\Resources\Mutations\Pages\CreateMutation;
use App\Filament\Resources\Mutations\Pages\EditMutation;
use App\Filament\Resources\Mutations\Pages\ListMutations;
use App\Filament\Resources\Mutations\Pages\ViewMutation;
use App\Models\Departement;
use App\Models\SiteAffectation;
use App\Models\Societe;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class MutationResource extends ModuleRhResource
{
    protected static ?int $navigationSort = 6;

    public static function module(): ModuleRh
    {
        return ModuleRh::Mutation;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedArrowsRightLeft;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            Select::make('nouvelle_societe_id')->label('Nouvelle société')->options(fn (): array => Societe::query()->orderBy('name')->pluck('name', 'id')->all())->live()->afterStateUpdated(fn (Set $set) => $set('nouveau_departement_id', null))->helperText('Laisser vide : inchangée.'),
            Select::make('nouveau_site_id')->label('Nouveau site')->options(fn (): array => SiteAffectation::query()->orderBy('name')->pluck('name', 'id')->all())->helperText('Laisser vide : inchangé.'),
            Select::make('nouveau_departement_id')->label('Nouveau département')->options(fn (Get $get): array => Departement::query()->when($get('nouvelle_societe_id'), fn ($q, $societe) => $q->where('societe_id', $societe))->orderBy('name')->pluck('name', 'id')->all())->searchable()->helperText('Laisser vide : inchangé.'),
            TextInput::make('nouvelle_fonction')->label('Nouvelle fonction')->maxLength(255),
            DatePicker::make('date_effet')->label('Date d\'effet')->required(),
            Textarea::make('motif')->label('Motif')->maxLength(2000)->columnSpanFull(),
        ];
    }

    protected static function affichage(): array
    {
        return [
            'ancienneSociete.name' => 'Société actuelle',
            'ancienSite.name' => 'Site actuel',
            'ancienDepartement.name' => 'Département actuel',
            'nouvelleSociete.name' => 'Nouvelle société',
            'nouveauSite.name' => 'Nouveau site',
            'nouveauDepartement.name' => 'Nouveau département',
            'nouvelle_fonction' => 'Nouvelle fonction',
            'date_effet' => 'Date d\'effet',
            'motif' => 'Motif',
            'appliquee_le' => 'Effet appliqué le',
        ];
    }
}
