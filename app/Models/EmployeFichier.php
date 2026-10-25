<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FichierCategorie;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeFichier extends Model
{
    protected $table = 'employe_fichiers';

    protected $fillable = ['employe_id', 'categorie', 'nom_original', 'path', 'mime', 'taille', 'depose_par'];

    /** @var list<string> */
    protected $hidden = ['path'];

    protected static function booted(): void
    {
        static::created(static function (EmployeFichier $fichier): void {
            app(AuditService::class)->journaliser('employe.fichier_ajoute', $fichier->employe, "Fichier ajouté au dossier de l'agent {$fichier->employe?->matricule}.", ['fichier_id' => $fichier->getKey(), 'categorie' => $fichier->categorie?->value]);
        });

        static::deleted(static function (EmployeFichier $fichier): void {
            app(AuditService::class)->journaliser('employe.fichier_supprime', $fichier->employe, "Fichier retiré du dossier de l'agent {$fichier->employe?->matricule}.", ['fichier_id' => $fichier->getKey(), 'categorie' => $fichier->categorie?->value]);
        });
    }

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
