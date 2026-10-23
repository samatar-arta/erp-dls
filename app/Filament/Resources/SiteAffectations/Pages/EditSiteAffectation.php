<?php

namespace App\Filament\Resources\SiteAffectations\Pages;

use App\Filament\Resources\SiteAffectations\SiteAffectationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiteAffectation extends EditRecord
{
    protected static string $resource = SiteAffectationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
