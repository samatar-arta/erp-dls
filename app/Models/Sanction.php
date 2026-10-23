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
class Sanction extends Model
{
    use HasWorkflow, VerifieCompatibiliteModele;

    public const TYPE_FK = 'type_sanction_id';

    public const MODELE_FK = 'model_sanction_id';

    public const TYPE_CLASS = TypeSanction::class;

    public const MODELE_CLASS = ModelSanction::class;

    protected $table = 'sanctions';

    /** @var list<string> */
    protected $fillable = ['matricule', 'type_sanction_id', 'model_sanction_id', 'niveau_sanction_id', 'cree_par', 'date_sanction', 'description', 'motif', 'sanction_precedente_id'];

    protected function casts(): array
    {
        return [
            'date_sanction' => 'date',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeSanction::class, 'type_sanction_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelSanction::class, 'model_sanction_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(NiveauSanction::class, 'niveau_sanction_id');
    }

    public function sanctionPrecedente(): BelongsTo
    {
        return $this->belongsTo(Sanction::class, 'sanction_precedente_id');
    }
}
