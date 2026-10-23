<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModulesRh\Pages;

use App\Exceptions\RegleMetierException;
use App\Services\EnregistrementRhService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

abstract class CreateEnregistrementRh extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(EnregistrementRhService::class)->creer(static::getResource()::module(), $data, auth()->user());
        } catch (RegleMetierException|AuthorizationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
