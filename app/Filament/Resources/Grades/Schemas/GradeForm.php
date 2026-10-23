<?php

declare(strict_types=1);

namespace App\Filament\Resources\Grades\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label('Code')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('name')->label('Libellé')->required()->maxLength(255),
                TextInput::make('niveau')->label('Niveau hiérarchique')->numeric()->integer()->minValue(0)->default(0),
                Toggle::make('is_active')->label('Actif')->default(true),
            ]);
    }
}
