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
class Document extends Model
{
    use HasWorkflow, VerifieCompatibiliteModele;

    public const TYPE_FK = 'type_document_id';

    public const MODELE_FK = 'model_document_id';

    public const TYPE_CLASS = TypeDocument::class;

    public const MODELE_CLASS = ModelDocument::class;

    protected $table = 'documents';

    /** @var list<string> */
    protected $fillable = ['matricule', 'type_document_id', 'model_document_id', 'cree_par', 'date_production', 'commentaire'];

    protected function casts(): array
    {
        return [
            'date_production' => 'date',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class, 'type_document_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelDocument::class, 'model_document_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
