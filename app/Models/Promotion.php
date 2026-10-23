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
class Promotion extends Model
{
    use HasWorkflow, VerifieCompatibiliteModele;

    public const TYPE_FK = 'type_promotion_id';

    public const MODELE_FK = 'model_promotion_id';

    public const TYPE_CLASS = TypePromotion::class;

    public const MODELE_CLASS = ModelPromotion::class;

    protected $table = 'promotions';

    /** @var list<string> */
    protected $fillable = ['matricule', 'type_promotion_id', 'model_promotion_id', 'cree_par', 'ancienne_fonction', 'nouvelle_fonction', 'ancien_grade_id', 'nouveau_grade_id', 'ancien_salaire', 'nouveau_salaire', 'date_effet', 'motif'];

    /** @var list<string> */
    protected $hidden = ['ancien_salaire', 'nouveau_salaire'];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
            'ancien_salaire' => 'decimal:2',
            'nouveau_salaire' => 'decimal:2',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypePromotion::class, 'type_promotion_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelPromotion::class, 'model_promotion_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function ancienGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'ancien_grade_id');
    }

    public function nouveauGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'nouveau_grade_id');
    }
}
