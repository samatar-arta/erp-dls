<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conges', function (Blueprint $table): void {
            // Demi-journée d'ouverture (départ en congé l'après-midi) et de clôture (retour l'après-midi).
            $table->boolean('demi_debut')->default(false)->after('date_fin');
            $table->boolean('demi_fin')->default(false)->after('demi_debut');
        });
    }

    public function down(): void
    {
        Schema::table('conges', function (Blueprint $table): void {
            $table->dropColumn(['demi_debut', 'demi_fin']);
        });
    }
};
