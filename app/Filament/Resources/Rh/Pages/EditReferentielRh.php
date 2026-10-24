<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Pages;

use App\Exceptions\RegleMetierException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

abstract class EditReferentielRh extends EditRecord
{
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $record->update($data);
        } catch (RegleMetierException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return $record;
    }
}
