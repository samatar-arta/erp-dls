<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gravité d'une sanction (avertissement, blâme, mise à pied…) ; distinct du type de sanction.
 */
class NiveauSanction extends Model
{
    protected $table = 'niveaux_sanctions';

    protected $fillable = ['code', 'name', 'ordre', 'is_active'];

    protected function casts(): array
    {
        return ['ordre' => 'integer', 'is_active' => 'boolean'];
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class, 'niveau_sanction_id');
    }
}
