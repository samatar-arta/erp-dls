<?php

namespace App\Filament\Resources\SiteDepartements\Pages;

use App\Filament\Resources\SiteDepartements\SiteDepartementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiteDepartement extends EditRecord
{
    protected static string $resource = SiteDepartementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
