<?php

declare(strict_types=1);

namespace App\Filament\Resources\Grades\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Libellé')->searchable(),
                TextColumn::make('niveau')->label('Niveau')->sortable(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->defaultSort('niveau')
            ->recordActions([EditAction::make()]);
    }
}
