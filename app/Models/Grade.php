<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $table = 'grades';

    protected $fillable = ['code', 'name', 'niveau', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'niveau' => 'integer'];
    }
}
