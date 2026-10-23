<?php

namespace App\Filament\Resources\SiteAffectations\Pages;

use App\Filament\Resources\SiteAffectations\SiteAffectationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiteAffectations extends ListRecords
{
    protected static string $resource = SiteAffectationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
