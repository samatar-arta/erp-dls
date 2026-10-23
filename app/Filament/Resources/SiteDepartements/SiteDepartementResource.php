<?php

declare(strict_types=1);

namespace App\Filament\Resources\SiteDepartements;

use App\Filament\Resources\SiteDepartements\Pages\CreateSiteDepartement;
use App\Filament\Resources\SiteDepartements\Pages\EditSiteDepartement;
use App\Filament\Resources\SiteDepartements\Pages\ListSiteDepartements;
use App\Filament\Resources\SiteDepartements\Schemas\SiteDepartementForm;
use App\Filament\Resources\SiteDepartements\Tables\SiteDepartementsTable;
use App\Models\SiteDepartement;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SiteDepartementResource extends Resource
{
    protected static ?string $model = SiteDepartement::class;

    protected static ?string $modelLabel = 'Site-département';

    protected static ?string $pluralModelLabel = 'Site-départements';

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    public static function form(Schema $schema): Schema
    {
        return SiteDepartementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteDepartementsTable::configure($table);
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
            'index' => ListSiteDepartements::route('/'),
            'create' => CreateSiteDepartement::route('/create'),
            'edit' => EditSiteDepartement::route('/{record}/edit'),
        ];
    }
}
