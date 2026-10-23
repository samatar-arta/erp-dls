<?php

declare(strict_types=1);

namespace App\Filament\Resources\TypeContrats\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TypeContratsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Libellé')->searchable()->sortable(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
