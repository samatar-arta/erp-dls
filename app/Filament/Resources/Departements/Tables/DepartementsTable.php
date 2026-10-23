<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departements\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DepartementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('societe.name')->label('Société')->sortable()->searchable(),
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('abrege')->label('Abrégé'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('societe_id')->label('Société')->relationship('societe', 'name'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
