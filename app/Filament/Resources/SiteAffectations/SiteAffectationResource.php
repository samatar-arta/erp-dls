<?php

declare(strict_types=1);

namespace App\Filament\Resources\SiteAffectations;

use App\Filament\Resources\SiteAffectations\Pages\CreateSiteAffectation;
use App\Filament\Resources\SiteAffectations\Pages\EditSiteAffectation;
use App\Filament\Resources\SiteAffectations\Pages\ListSiteAffectations;
use App\Filament\Resources\SiteAffectations\Schemas\SiteAffectationForm;
use App\Filament\Resources\SiteAffectations\Tables\SiteAffectationsTable;
use App\Models\SiteAffectation;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SiteAffectationResource extends Resource
{
    protected static ?string $model = SiteAffectation::class;

    protected static ?string $modelLabel = "Site d'affectation";

    protected static ?string $pluralModelLabel = "Sites d'affectation";

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    public static function form(Schema $schema): Schema
    {
        return SiteAffectationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteAffectationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteAffectations::route('/'),
            'create' => CreateSiteAffectation::route('/create'),
            'edit' => EditSiteAffectation::route('/{record}/edit'),
        ];
    }
}
