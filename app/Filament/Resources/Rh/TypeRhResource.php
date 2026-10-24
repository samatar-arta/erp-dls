<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh;

use App\Enums\ModuleRh;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Administration des types d'un module (documents, congés...). Un type appartient à un seul module ;
 * le code est un identifiant stable (non modifiable après création).
 */
abstract class TypeRhResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Types et modèles';

    abstract public static function module(): ModuleRh;

    /** @return list<\Filament\Schemas\Components\Component> */
    protected static function champsSpecifiques(): array
    {
        return [];
    }

    /** @return list<\Filament\Tables\Columns\Column> */
    protected static function colonnesSpecifiques(): array
    {
        return [];
    }

    public static function getModel(): string
    {
        return static::module()->typeClass();
    }

    public static function getModelLabel(): string
    {
        return 'Type de '.mb_strtolower(static::module()->libelleSingulier());
    }

    public static function getPluralModelLabel(): string
    {
        return 'Types — '.static::module()->label();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedTag;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(60)
                ->regex('/^[A-Z0-9_]+$/')->validationMessages(['regex' => 'Majuscules, chiffres et _ uniquement.'])
                ->unique(ignoreRecord: true)
                ->disabled(fn (string $operation): bool => $operation !== 'create')
                ->dehydrated(fn (string $operation): bool => $operation === 'create'),
            TextInput::make('name')->label('Libellé')->required()->maxLength(255),
            ...static::champsSpecifiques(),
            Toggle::make('is_active')->label('Actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Libellé')->searchable(),
                ...static::colonnesSpecifiques(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        $espace = (new \ReflectionClass(static::class))->getNamespaceName().'\\Pages\\';
        $base = Str::beforeLast(class_basename(static::class), 'Resource');

        return [
            'index' => ($espace.'List'.$base.'s')::route('/'),
            'create' => ($espace.'Create'.$base)::route('/create'),
            'edit' => ($espace.'Edit'.$base)::route('/{record}/edit'),
        ];
    }
}
