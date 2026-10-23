<?php

declare(strict_types=1);

namespace App\Filament\Resources\TypeContrats;

use App\Filament\Resources\TypeContrats\Pages\CreateTypeContrat;
use App\Filament\Resources\TypeContrats\Pages\EditTypeContrat;
use App\Filament\Resources\TypeContrats\Pages\ListTypeContrats;
use App\Filament\Resources\TypeContrats\Schemas\TypeContratForm;
use App\Filament\Resources\TypeContrats\Tables\TypeContratsTable;
use App\Models\TypeContrat;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TypeContratResource extends Resource
{
    protected static ?string $model = TypeContrat::class;

    protected static ?string $modelLabel = 'Type de contrat';

    protected static ?string $pluralModelLabel = 'Types de contrats';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return TypeContratForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TypeContratsTable::configure($table);
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
            'index' => ListTypeContrats::route('/'),
            'create' => CreateTypeContrat::route('/create'),
            'edit' => EditTypeContrat::route('/{record}/edit'),
        ];
    }
}
