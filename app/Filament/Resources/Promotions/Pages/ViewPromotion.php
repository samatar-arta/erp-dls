<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\ModulesRh\Pages\ViewEnregistrementRh;

class ViewPromotion extends ViewEnregistrementRh
{
    protected static string $resource = PromotionResource::class;
}
