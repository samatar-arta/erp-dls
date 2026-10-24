<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Types\Pages;

use App\Filament\Resources\Rh\Types\TypePromotionResource;
use App\Filament\Resources\Rh\Pages\CreateReferentielRh;

class CreateTypePromotion extends CreateReferentielRh
{
    protected static string $resource = TypePromotionResource::class;
}
