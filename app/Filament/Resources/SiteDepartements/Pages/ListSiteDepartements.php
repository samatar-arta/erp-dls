<?php

namespace App\Filament\Resources\SiteDepartements\Pages;

use App\Filament\Resources\SiteDepartements\SiteDepartementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiteDepartements extends ListRecords
{
    protected static string $resource = SiteDepartementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
