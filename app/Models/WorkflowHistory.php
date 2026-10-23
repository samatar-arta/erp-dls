<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkflowAction;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique en ajout seul : toute modification ou suppression est refusée, quel que soit le rôle.
 */
class WorkflowHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_histories';

    /** @var list<string> */
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new RegleMetierException('L\'historique du workflow est immuable.');
        });

        static::deleting(static function (): never {
            throw new RegleMetierException('L\'historique du workflow est immuable.');
        });
    }

    protected function casts(): array
    {
        return [
            'action' => WorkflowAction::class,
            'de_statut' => WorkflowStatut::class,
            'vers_statut' => WorkflowStatut::class,
            'created_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
