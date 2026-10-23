<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FichierCategorie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeFichier extends Model
{
    protected $table = 'employe_fichiers';

    protected $fillable = ['employe_id', 'categorie', 'nom_original', 'path', 'mime', 'taille', 'depose_par'];

    /** @var list<string> */
    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['categorie' => FichierCategorie::class, 'taille' => 'integer'];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function deposePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }
}
