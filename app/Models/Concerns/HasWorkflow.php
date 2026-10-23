<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Auth;

/**
 * Rattache un enregistrement métier au workflow centralisé.
 *  - un workflow BROUILLON est créé avec l'enregistrement ;
 *  - la référence, une fois attribuée, est immuable (aucun rôle ne peut la modifier ni la retirer) ;
 *  - seul un brouillon sans référence peut être supprimé.
 */
trait HasWorkflow
{
    protected static function bootHasWorkflow(): void
    {
        static::created(static function (Model $enregistrement): void {
            $workflow = new Workflow;
            $workflow->forceFill([
                'workflowable_type' => $enregistrement->getMorphClass(),
                'workflowable_id' => $enregistrement->getKey(),
                'statut' => WorkflowStatut::Brouillon,
                'cree_par_id' => Auth::id(),
            ])->save();
        });

        static::updating(static function (Model $enregistrement): void {
            if ($enregistrement->isDirty('reference') && $enregistrement->getOriginal('reference') !== null) {
                throw new RegleMetierException('La référence est définitive : elle ne peut être ni modifiée ni retirée.');
            }
        });

        static::deleting(static function (Model $enregistrement): void {
            $workflow = Workflow::query()
                ->where('workflowable_type', $enregistrement->getMorphClass())
                ->where('workflowable_id', $enregistrement->getKey())
                ->first();

            if ($enregistrement->getAttribute('reference') !== null
                || ($workflow !== null && $workflow->statut !== WorkflowStatut::Brouillon)) {
                throw new RegleMetierException('Seul un brouillon sans référence peut être supprimé.');
            }
        });

        static::deleted(static function (Model $enregistrement): void {
            // Requête directe : l'historique du brouillon disparaît avec lui (cascade en base).
            Workflow::query()
                ->where('workflowable_type', $enregistrement->getMorphClass())
                ->where('workflowable_id', $enregistrement->getKey())
                ->delete();
        });
    }

    public function workflow(): MorphOne
    {
        return $this->morphOne(Workflow::class, 'workflowable');
    }

    public function statutWorkflow(): ?WorkflowStatut
    {
        return $this->workflow?->statut;
    }
}
