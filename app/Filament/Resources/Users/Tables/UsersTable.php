<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\RoleName;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        $roles = [];
        foreach (RoleName::cases() as $role) {
            $roles[$role->value] = $role->value;
        }

        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('roles.name')->label('Rôles')->badge(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rôle')->options($roles)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->role($data['value'])
                        : $query),
                TernaryFilter::make('is_active')->label('Actif'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
