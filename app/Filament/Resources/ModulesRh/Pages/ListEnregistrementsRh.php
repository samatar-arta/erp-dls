<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModulesRh\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

abstract class ListEnregistrementsRh extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
