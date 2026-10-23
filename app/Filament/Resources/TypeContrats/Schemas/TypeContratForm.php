<?php

declare(strict_types=1);

namespace App\Filament\Resources\TypeContrats\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TypeContratForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Libellé')->required()->maxLength(255)->unique(ignoreRecord: true),
                Textarea::make('description')->label('Description')->columnSpanFull(),
                Toggle::make('is_active')->label('Actif')->default(true),
            ]);
    }
}
