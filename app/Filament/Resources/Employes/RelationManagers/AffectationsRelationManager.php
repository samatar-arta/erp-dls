<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Historique des affectations en lecture seule : il s'alimente uniquement par les mutations,
 * promotions approuvées et la création de l'agent.
 */
class AffectationsRelationManager extends RelationManager
{
    protected static string $relationship = 'affectations';

    protected static ?string $title = 'Historique des affectations';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_debut')->label('Début')->date('d/m/Y')->sortable(),
                TextColumn::make('date_fin')->label('Fin')->date('d/m/Y')->placeholder('En cours'),
                TextColumn::make('societe.name')->label('Société'),
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('departement.name')->label('Département'),
                TextColumn::make('fonction')->label('Fonction')->placeholder('—'),
                TextColumn::make('source')->label('Origine')->badge(),
            ])
            ->defaultSort('date_debut', 'desc');
    }
}
