<?php

namespace App\Filament\Resources\Societes\Pages;

use App\Filament\Resources\Societes\SocieteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSociete extends EditRecord
{
    protected static string $resource = SocieteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
