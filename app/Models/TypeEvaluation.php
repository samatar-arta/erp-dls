<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeEvaluation extends Model
{
    protected $table = 'types_evaluations';

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function modeles(): HasMany
    {
        return $this->hasMany(ModelEvaluation::class, 'type_evaluation_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'type_evaluation_id');
    }

    public function modeleParDefaut(): ?ModelEvaluation
    {
        return $this->modeles()->where('is_default', true)->where('is_active', true)->first();
    }
}
