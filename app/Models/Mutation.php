<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\VerifieCompatibiliteModele;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Champs gérés par les services (jamais en saisie directe) : reference, contenu_genere,
 * version_modele, fichier_path, statut_generation.
 */
class Mutation extends Model
{
    use HasWorkflow, VerifieCompatibiliteModele;

    public const TYPE_FK = 'type_mutation_id';

    public const MODELE_FK = 'model_mutation_id';

    public const TYPE_CLASS = TypeMutation::class;

    public const MODELE_CLASS = ModelMutation::class;

    protected $table = 'mutations';

    /** @var list<string> */
    protected $fillable = ['matricule', 'type_mutation_id', 'model_mutation_id', 'cree_par', 'ancien_site_id', 'nouveau_site_id', 'ancien_departement_id', 'nouveau_departement_id', 'ancienne_societe_id', 'nouvelle_societe_id', 'ancienne_fonction', 'nouvelle_fonction', 'date_effet', 'motif'];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
            'appliquee_le' => 'datetime',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeMutation::class, 'type_mutation_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelMutation::class, 'model_mutation_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function ancienSite(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'ancien_site_id');
    }

    public function nouveauSite(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'nouveau_site_id');
    }

    public function ancienDepartement(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'ancien_departement_id');
    }

    public function nouveauDepartement(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'nouveau_departement_id');
    }

    public function ancienneSociete(): BelongsTo
    {
        return $this->belongsTo(Societe::class, 'ancienne_societe_id');
    }

    public function nouvelleSociete(): BelongsTo
    {
        return $this->belongsTo(Societe::class, 'nouvelle_societe_id');
    }
}
