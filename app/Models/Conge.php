<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotifPermission;
use App\Enums\QualificationConge;
use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\VerifieCompatibiliteModele;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Champs gérés par les services (jamais en saisie directe) : reference, contenu_genere,
 * version_modele, fichier_path, statut_generation.
 */
class Conge extends Model
{
    use HasWorkflow, VerifieCompatibiliteModele;

    public const TYPE_FK = 'type_conge_id';

    public const MODELE_FK = 'model_conge_id';

    public const TYPE_CLASS = TypeConge::class;

    public const MODELE_CLASS = ModelConge::class;

    protected $table = 'conges';

    /** @var list<string> */
    protected $fillable = ['matricule', 'type_conge_id', 'model_conge_id', 'user_id', 'date_debut', 'date_fin', 'demi_debut', 'demi_fin', 'nbre_jours', 'qualification', 'pendant_heures_travail', 'motif_permission', 'motif'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'demi_debut' => 'boolean',
            'demi_fin' => 'boolean',
            'nbre_jours' => 'decimal:1',
            'qualification' => QualificationConge::class,
            'pendant_heures_travail' => 'boolean',
            'motif_permission' => MotifPermission::class,
        ];
    }

    /** Libellés pour les modèles de documents (la liste blanche de variables lit des propriétés). */
    public function getQualificationLibelleAttribute(): ?string
    {
        return $this->qualification?->label();
    }

    public function getMotifPermissionLibelleAttribute(): ?string
    {
        return $this->motif_permission?->label();
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeConge::class, 'type_conge_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelConge::class, 'model_conge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
