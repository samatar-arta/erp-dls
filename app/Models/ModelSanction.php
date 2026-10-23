<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\EstUnModele;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelSanction extends Model
{
    use EstUnModele;

    public const TYPE_FK = 'type_sanction_id';

    protected $table = 'model_sanctions';

    /** La version est gérée par le système (incrément si le modèle est déjà utilisé). */
    protected $fillable = ['type_sanction_id', 'titre', 'contenu', 'is_default', 'is_active', 'cree_par'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeSanction::class, 'type_sanction_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    protected function usages(): HasMany
    {
        return $this->hasMany(Sanction::class, 'model_sanction_id');
    }
}
