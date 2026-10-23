<?php

declare(strict_types=1);

namespace App\Filament\Resources\Societes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SocieteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nom')->required()->maxLength(255),
                TextInput::make('code')->label('Code')->maxLength(20)->unique(ignoreRecord: true),
                Textarea::make('description')->label('Description')->columnSpanFull(),
                Toggle::make('is_active')->label('Active')->default(true),
            ]);
    }
}
