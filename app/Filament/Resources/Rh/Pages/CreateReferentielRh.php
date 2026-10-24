<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh\Pages;

use App\Exceptions\RegleMetierException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

abstract class CreateReferentielRh extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        $modele = new (static::getModel());

        // Auteur des modèles (colonne présente uniquement sur les tables de modèles).
        if (in_array('cree_par', $modele->getFillable(), true)) {
            $data['cree_par'] = auth()->id();
        }

        try {
            return static::getResource()::getModel()::query()->create($data);
        } catch (RegleMetierException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
