<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departement extends Model
{
    protected $table = 'departements';

    protected $fillable = ['name', 'abrege', 'societe_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function siteDepartements(): HasMany
    {
        return $this->hasMany(SiteDepartement::class);
    }

    public function employes(): HasMany
    {
        return $this->hasMany(Employe::class);
    }
}
