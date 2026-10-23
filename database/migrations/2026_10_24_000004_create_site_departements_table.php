<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_departements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites_affectation')->cascadeOnDelete();
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->unsignedBigInteger('departement_id');
            $table->timestamps();

            $table->unique(['site_id', 'departement_id']);
            // Garantit que le département appartient bien à la société indiquée.
            $table->foreign(['departement_id', 'societe_id'])
                ->references(['id', 'societe_id'])->on('departements')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_departements');
    }
};
