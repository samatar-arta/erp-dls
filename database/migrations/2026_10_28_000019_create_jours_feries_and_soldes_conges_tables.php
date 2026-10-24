<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jours_feries', function (Blueprint $table): void {
            $table->id();
            // Pour un jour récurrent, seule la combinaison mois / jour compte (l'année de la date est indicative).
            $table->date('date_jour')->unique();
            $table->string('libelle');
            $table->boolean('recurrent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('soldes_conges', function (Blueprint $table): void {
            $table->id();
            $table->string('matricule', 30);
            $table->foreignId('type_conge_id')->constrained('types_conges')->restrictOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->decimal('jours_acquis', 5, 1)->default(0);
            $table->decimal('jours_report', 5, 1)->default(0);
            $table->decimal('jours_pris', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['matricule', 'type_conge_id', 'annee']);
            $table->foreign('matricule')->references('matricule')->on('employes')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soldes_conges');
        Schema::dropIfExists('jours_feries');
    }
};
