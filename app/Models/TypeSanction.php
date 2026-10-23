<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeSanction extends Model
{
    protected $table = 'types_sanctions';

    protected $fillable = ['code', 'name', 'description', 'requires_conseil_discipline', 'requires_workflow', 'is_active'];

    protected function casts(): array
    {
        return [
            'requires_conseil_discipline' => 'boolean',
            'requires_workflow' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function modeles(): HasMany
    {
        return $this->hasMany(ModelSanction::class, 'type_sanction_id');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class, 'type_sanction_id');
    }

    public function modeleParDefaut(): ?ModelSanction
    {
        return $this->modeles()->where('is_default', true)->where('is_active', true)->first();
    }
}
