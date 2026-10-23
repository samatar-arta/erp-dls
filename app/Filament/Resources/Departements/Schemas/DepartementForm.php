<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class DepartementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('societe_id')
                    ->label('Société')
                    ->relationship('societe', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, callable $get): Unique => $rule->where('societe_id', $get('societe_id')),
                    ),
                TextInput::make('abrege')->label('Abrégé')->maxLength(30),
                Toggle::make('is_active')->label('Actif')->default(true),
            ]);
    }
}
