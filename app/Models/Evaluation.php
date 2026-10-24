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
class Evaluation extends Model
{
    use HasWorkflow, VerifieCompatibiliteModele;

    public const TYPE_FK = 'type_evaluation_id';

    public const MODELE_FK = 'model_evaluation_id';

    public const TYPE_CLASS = TypeEvaluation::class;

    public const MODELE_CLASS = ModelEvaluation::class;

    protected $table = 'evaluations';

    /** @var list<string> */
    protected $fillable = ['matricule', 'type_evaluation_id', 'model_evaluation_id', 'evaluateur_id', 'date_evaluation', 'periode_debut', 'periode_fin', 'notes_criteres', 'appreciation', 'objectifs'];

    protected function casts(): array
    {
        return [
            'date_evaluation' => 'date',
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'notes_criteres' => 'array',
            'note' => 'decimal:2',
        ];
    }

    /** Détail des notes par critère en une ligne de texte (variable de modèle « evaluation.criteres »). */
    public function getCriteresResumeAttribute(): string
    {
        return collect($this->notes_criteres ?? [])
            ->map(fn (array $c): string => sprintf(
                '%s : %s/%s (coef. %s)',
                $c['libelle'],
                $this->formaterNombre((float) $c['note']),
                $this->formaterNombre((float) $c['note_max']),
                $this->formaterNombre((float) $c['coefficient']),
            ))
            ->implode(' ; ');
    }

    private function formaterNombre(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 2, ',', ''), '0'), ',');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeEvaluation::class, 'type_evaluation_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelEvaluation::class, 'model_evaluation_id');
    }

    public function evaluateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluateur_id');
    }
}
