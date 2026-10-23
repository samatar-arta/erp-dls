<?php

namespace App\Filament\Resources\TypeContrats\Pages;

use App\Filament\Resources\TypeContrats\TypeContratResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTypeContrat extends EditRecord
{
    protected static string $resource = TypeContratResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
