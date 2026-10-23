<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Exceptions\RegleMetierException;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserManagementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $utilisateur */
        $utilisateur = $this->getRecord();
        $data['roles'] = $utilisateur->roles()->pluck('name')->all();
        $data['site_departement_ids'] = $utilisateur->siteDepartements()->pluck('site_departements.id')->all();

        return $data;
    }

    /**
     * Toute modification de rôle, d'affectation ou d'activation passe par le service (garde-fous).
     *
     * @param  User  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $acteur = auth()->user();
        $service = app(UserManagementService::class);
        $roles = $data['roles'] ?? null;
        $affectations = $data['site_departement_ids'] ?? null;
        $actif = $data['is_active'] ?? null;
        unset($data['roles'], $data['site_departement_ids'], $data['is_active']);

        try {
            DB::transaction(function () use ($record, $data, $acteur, $service, $roles, $affectations, $actif): void {
                $record->update($data);

                if ($record->isNot($acteur)) {
                    if ($actif !== null && $actif !== $record->is_active) {
                        $service->definirActif($acteur, $record, (bool) $actif);
                    }
                    if ($roles !== null && $roles !== []) {
                        $service->mettreAJourRoles($acteur, $record, $roles);
                    }
                    if ($affectations !== null) {
                        $service->mettreAJourAffectations($acteur, $record, $affectations);
                    }
                }
            });
        } catch (RegleMetierException|AuthorizationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return $record;
    }
}
