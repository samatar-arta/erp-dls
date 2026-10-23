<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModulesRh\Pages;

use App\Exceptions\RegleMetierException;
use App\Services\EnregistrementRhService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

abstract class EditEnregistrementRh extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(EnregistrementRhService::class)->mettreAJour($record, $data, auth()->user());
        } catch (RegleMetierException|AuthorizationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
