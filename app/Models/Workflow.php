<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkflowStatut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Écrit uniquement par WorkflowService (aucun champ en saisie directe).
 */
class Workflow extends Model
{
    /** @var list<string> */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'statut' => WorkflowStatut::class,
            'echeance_at' => 'datetime',
        ];
    }

    public function workflowable(): MorphTo
    {
        return $this->morphTo();
    }

    public function historiques(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class)->orderBy('id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }

    public function soumetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soumis_par_id');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_id');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par_id');
    }
}
