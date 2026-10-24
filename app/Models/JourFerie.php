<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JourFerie extends Model
{
    protected $table = 'jours_feries';

    protected $fillable = ['date_jour', 'libelle', 'recurrent', 'is_active'];

    protected function casts(): array
    {
        return ['date_jour' => 'date', 'recurrent' => 'boolean', 'is_active' => 'boolean'];
    }
}
