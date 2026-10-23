<?php

declare(strict_types=1);

namespace App\Filament\Resources\SiteDepartements\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SiteDepartementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('Site')->sortable()->searchable(),
                TextColumn::make('societe.name')->label('Société')->sortable()->searchable(),
                TextColumn::make('departement.name')->label('Département')->sortable()->searchable(),
            ])
            ->filters([
                SelectFilter::make('site_id')->label('Site')->relationship('site', 'name'),
                SelectFilter::make('societe_id')->label('Société')->relationship('societe', 'name'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
