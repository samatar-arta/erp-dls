<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeAffectation extends Model
{
    protected $table = 'employe_affectations';

    protected $fillable = [
        'employe_id', 'societe_id', 'site_id', 'departement_id', 'fonction',
        'date_debut', 'date_fin', 'source', 'cree_par',
    ];

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date'];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'site_id');
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }
}
