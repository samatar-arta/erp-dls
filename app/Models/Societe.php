<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Societe extends Model
{
    protected $table = 'societes';

    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function departements(): HasMany
    {
        return $this->hasMany(Departement::class);
    }

    public function employes(): HasMany
    {
        return $this->hasMany(Employe::class);
    }
}
