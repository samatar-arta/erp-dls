<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles;

use App\Enums\ModuleRh;
use App\Filament\Resources\Rh\ModeleRhResource;

class ModelDocumentResource extends ModeleRhResource
{
    public static function module(): ModuleRh
    {
        return ModuleRh::Document;
    }
}
