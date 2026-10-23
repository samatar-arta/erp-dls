<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employes\Pages;

use App\Exceptions\RegleMetierException;
use App\Filament\Resources\Employes\EmployeResource;
use App\Models\SiteDepartement;
use App\Services\EmployeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateEmploye extends CreateRecord
{
    protected static string $resource = EmployeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $couple = SiteDepartement::query()->find($data['site_departement_id'] ?? null);
        unset($data['site_departement_id']);

        try {
            if ($couple === null) {
                throw new RegleMetierException('Affectation invalide.');
            }

            return app(EmployeService::class)->creer(
                $data + ['societe_id' => $couple->societe_id, 'site_id' => $couple->site_id, 'departement_id' => $couple->departement_id],
                auth()->user(),
            );
        } catch (RegleMetierException|AuthorizationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
