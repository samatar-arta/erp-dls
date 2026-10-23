<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteAffectation extends Model
{
    protected $table = 'sites_affectation';

    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function siteDepartements(): HasMany
    {
        return $this->hasMany(SiteDepartement::class, 'site_id');
    }

    public function employes(): HasMany
    {
        return $this->hasMany(Employe::class, 'site_id');
    }
}
