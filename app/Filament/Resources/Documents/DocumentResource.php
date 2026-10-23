<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents;

use App\Enums\ModuleRh;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

class DocumentResource extends ModuleRhResource
{
    protected static ?int $navigationSort = 1;

    public static function module(): ModuleRh
    {
        return ModuleRh::Document;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedDocumentText;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            DatePicker::make('date_production')->label('Date de production')->required()->default(now()),
            Textarea::make('commentaire')->label('Commentaire')->maxLength(2000)->columnSpanFull(),
        ];
    }

    protected static function affichage(): array
    {
        return [
            'date_production' => 'Date de production',
            'commentaire' => 'Commentaire',
        ];
    }
}
