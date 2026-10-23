<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModeCalcul;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeConge extends Model
{
    protected $table = 'types_conges';

    protected $fillable = ['code', 'name', 'color', 'default_days_per_year', 'requires_justificatif', 'requires_workflow', 'mode_calcul', 'is_active'];

    protected function casts(): array
    {
        return [
            'default_days_per_year' => 'decimal:1',
            'requires_justificatif' => 'boolean',
            'requires_workflow' => 'boolean',
            'mode_calcul' => ModeCalcul::class,
            'is_active' => 'boolean',
        ];
    }

    public function modeles(): HasMany
    {
        return $this->hasMany(ModelConge::class, 'type_conge_id');
    }

    public function conges(): HasMany
    {
        return $this->hasMany(Conge::class, 'type_conge_id');
    }

    public function modeleParDefaut(): ?ModelConge
    {
        return $this->modeles()->where('is_default', true)->where('is_active', true)->first();
    }
}
