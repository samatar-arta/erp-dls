<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SiteDepartement extends Model
{
    protected $table = 'site_departements';

    protected $fillable = ['site_id', 'societe_id', 'departement_id'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'site_id');
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_departement_user')->withTimestamps();
    }

    public function libelle(): string
    {
        return sprintf(
            '%s / %s / %s',
            $this->societe?->name,
            $this->site?->name,
            $this->departement?->abrege ?: $this->departement?->name,
        );
    }
}
