<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employes\Pages;

use App\Exceptions\RegleMetierException;
use App\Filament\Resources\Employes\EmployeResource;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditEmploye extends EditRecord
{
    protected static string $resource = EmployeResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // L'affectation (société, site, département, fonction) n'est jamais modifiable ici.
        unset($data['societe_id'], $data['site_id'], $data['departement_id'], $data['fonction'], $data['matricule']);

        try {
            $record->update($data);
        } catch (RegleMetierException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return $record;
    }
}
