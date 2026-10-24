<?php

declare(strict_types=1);

namespace App\Filament\Resources\JoursFeries;

use App\Filament\Resources\JoursFeries\Pages\ManageJoursFeries;
use App\Models\JourFerie;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class JourFerieResource extends Resource
{
    protected static ?string $model = JourFerie::class;

    protected static ?string $modelLabel = 'Jour férié';

    protected static ?string $pluralModelLabel = 'Jours fériés';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date_jour')->label('Date')->required()->unique(ignoreRecord: true),
            TextInput::make('libelle')->label('Libellé')->required()->maxLength(255),
            Toggle::make('recurrent')->label('Chaque année à la même date')
                ->helperText('À cocher pour les fêtes à date fixe. Les fêtes religieuses mobiles se saisissent chaque année.'),
            Toggle::make('is_active')->label('Actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_jour')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('libelle')->label('Libellé')->searchable(),
                IconColumn::make('recurrent')->label('Annuel')->boolean(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->defaultSort('date_jour', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageJoursFeries::route('/')];
    }
}
