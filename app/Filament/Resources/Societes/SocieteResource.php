<?php

declare(strict_types=1);

namespace App\Filament\Resources\Societes;

use App\Filament\Resources\Societes\Pages\CreateSociete;
use App\Filament\Resources\Societes\Pages\EditSociete;
use App\Filament\Resources\Societes\Pages\ListSocietes;
use App\Filament\Resources\Societes\Schemas\SocieteForm;
use App\Filament\Resources\Societes\Tables\SocietesTable;
use App\Models\Societe;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SocieteResource extends Resource
{
    protected static ?string $model = Societe::class;

    protected static ?string $modelLabel = 'Société';

    protected static ?string $pluralModelLabel = 'Sociétés';

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function form(Schema $schema): Schema
    {
        return SocieteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocietesTable::configure($table);
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
            'index' => ListSocietes::route('/'),
            'create' => CreateSociete::route('/create'),
            'edit' => EditSociete::route('/{record}/edit'),
        ];
    }
}
