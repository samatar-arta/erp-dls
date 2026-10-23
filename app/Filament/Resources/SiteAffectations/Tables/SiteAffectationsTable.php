<?php

declare(strict_types=1);

namespace App\Filament\Resources\SiteAffectations\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiteAffectationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('description')->label('Description')->limit(60),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
