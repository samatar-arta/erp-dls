<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Modeles\Pages;

use App\Filament\Resources\Rh\Modeles\ModelPromotionResource;
use App\Filament\Resources\Rh\Pages\ListReferentielRh;

class ListModelPromotions extends ListReferentielRh
{
    protected static string $resource = ModelPromotionResource::class;
}
