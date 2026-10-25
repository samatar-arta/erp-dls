<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seuls jours_acquis et jours_report sont ajustables à la main ; jours_pris n'évolue qu'à l'approbation
 * d'un congé (CongeService).
 */
class SoldeConge extends Model
{
    protected $table = 'soldes_conges';

    /** @var list<string> */
    protected $fillable = ['jours_acquis', 'jours_report'];

    protected static function booted(): void
    {
        // Tout changement (ajustement manuel ou débit à l'approbation) est tracé avec l'avant / après.
        static::updated(static function (SoldeConge $solde): void {
            $champs = array_intersect_key($solde->getChanges(), array_flip(['jours_acquis', 'jours_report', 'jours_pris']));
            if ($champs !== []) {
                app(AuditService::class)->journaliser('solde_conge.modifie', $solde, "Solde de congé {$solde->annee} de l'agent {$solde->matricule} modifié.", [
                    'avant' => array_intersect_key($solde->getOriginal(), $champs),
                    'apres' => $champs,
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'jours_acquis' => 'decimal:1',
            'jours_report' => 'decimal:1',
            'jours_pris' => 'decimal:1',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeConge::class, 'type_conge_id');
    }

    public function restant(): float
    {
        return (float) $this->jours_acquis + (float) $this->jours_report - (float) $this->jours_pris;
    }
}
