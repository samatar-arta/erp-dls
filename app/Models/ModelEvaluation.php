<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\EstUnModele;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelEvaluation extends Model
{
    use EstUnModele;

    public const TYPE_FK = 'type_evaluation_id';

    protected $table = 'model_evaluations';

    /** La version est gérée par le système (incrément si le modèle est déjà utilisé). */
    protected $fillable = ['type_evaluation_id', 'titre', 'contenu', 'criteres', 'is_default', 'is_active', 'cree_par'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'criteres' => 'array',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeEvaluation::class, 'type_evaluation_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    protected function usages(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'model_evaluation_id');
    }
}
