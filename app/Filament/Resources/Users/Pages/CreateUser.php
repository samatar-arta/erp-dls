<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Exceptions\RegleMetierException;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserManagementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $roles = $data['roles'] ?? [];
        $affectations = $data['site_departement_ids'] ?? [];
        unset($data['roles'], $data['site_departement_ids']);

        try {
            return DB::transaction(function () use ($data, $roles, $affectations): User {
                $service = app(UserManagementService::class);
                $utilisateur = User::query()->create($data);
                $service->mettreAJourRoles(auth()->user(), $utilisateur, $roles);
                $service->mettreAJourAffectations(auth()->user(), $utilisateur, $affectations);

                return $utilisateur;
            });
        } catch (RegleMetierException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
