<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Journal d'audit en lecture seule (aucune création, modification ni suppression par l'interface).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $modelLabel = 'Entrée du journal';

    protected static ?string $pluralModelLabel = 'Journal d\'audit';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('acteur')->label('Acteur')->placeholder('Système')->searchable(),
                TextColumn::make('action')->label('Action')->badge()->searchable(),
                TextColumn::make('cible_type')->label('Objet')->placeholder('—'),
                TextColumn::make('cible_id')->label('Identifiant')->placeholder('—')->searchable(),
                TextColumn::make('description')->label('Description')->wrap()->searchable(),
                TextColumn::make('adresse_ip')->label('IP')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label('Action')->searchable()
                    ->options(fn (): array => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('cible_type')->label('Objet')
                    ->options(fn (): array => AuditLog::query()->whereNotNull('cible_type')->distinct()->orderBy('cible_type')->pluck('cible_type', 'cible_type')->all()),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
