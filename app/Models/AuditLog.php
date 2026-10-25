<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\RegleMetierException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'audit en ajout seul : écriture réservée à AuditService, modification et suppression refusées
 * pour tous les rôles.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    /** @var list<string> */
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new RegleMetierException('Le journal d\'audit est immuable.');
        });

        static::deleting(static function (): never {
            throw new RegleMetierException('Le journal d\'audit est immuable.');
        });
    }

    protected function casts(): array
    {
        return ['donnees' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
