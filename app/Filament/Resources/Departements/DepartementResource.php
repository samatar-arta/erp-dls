<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departements;

use App\Filament\Resources\Departements\Pages\CreateDepartement;
use App\Filament\Resources\Departements\Pages\EditDepartement;
use App\Filament\Resources\Departements\Pages\ListDepartements;
use App\Filament\Resources\Departements\Schemas\DepartementForm;
use App\Filament\Resources\Departements\Tables\DepartementsTable;
use App\Models\Departement;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DepartementResource extends Resource
{
    protected static ?string $model = Departement::class;

    protected static ?string $modelLabel = 'Département';

    protected static ?string $pluralModelLabel = 'Départements';

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    public static function form(Schema $schema): Schema
    {
        return DepartementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartementsTable::configure($table);
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
            'index' => ListDepartements::route('/'),
            'create' => CreateDepartement::route('/create'),
            'edit' => EditDepartement::route('/{record}/edit'),
        ];
    }
}
