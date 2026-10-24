<?php

declare(strict_types=1);

namespace App\Filament\Resources\JoursFeries\Pages;

use App\Filament\Resources\JoursFeries\JourFerieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJoursFeries extends ManageRecords
{
    protected static string $resource = JourFerieResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
