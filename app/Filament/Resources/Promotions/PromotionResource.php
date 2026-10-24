<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions;

use App\Enums\ModuleRh;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\Pages\ViewPromotion;
use App\Models\Grade;
use App\Support\Permissions;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

class PromotionResource extends ModuleRhResource
{
    protected static ?int $navigationSort = 5;

    public static function module(): ModuleRh
    {
        return ModuleRh::Promotion;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedArrowTrendingUp;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            TextInput::make('nouvelle_fonction')->label('Nouvelle fonction')->required()->maxLength(255),
            Select::make('nouveau_grade_id')->label('Nouveau grade')->options(fn (): array => Grade::query()->where('is_active', true)->orderBy('niveau')->pluck('name', 'id')->all()),
            DatePicker::make('date_effet')->label('Date d\'effet')->required(),
            TextInput::make('ancien_salaire')->label('Salaire actuel')->numeric()->visible(fn (): bool => (bool) auth()->user()?->can(Permissions::SALAIRES_VIEW)),
            TextInput::make('nouveau_salaire')->label('Nouveau salaire')->numeric()->visible(fn (): bool => (bool) auth()->user()?->can(Permissions::SALAIRES_VIEW)),
            Textarea::make('motif')->label('Motif')->maxLength(2000)->columnSpanFull(),
        ];
    }

    protected static function affichage(): array
    {
        return [
            'ancienne_fonction' => 'Fonction actuelle',
            'nouvelle_fonction' => 'Nouvelle fonction',
            'nouveauGrade.name' => 'Nouveau grade',
            'date_effet' => 'Date d\'effet',
            'motif' => 'Motif',
            'appliquee_le' => 'Effet appliqué le',
        ];
    }
}
