<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_conges', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->decimal('default_days_per_year', 5, 1)->default(0);
            $table->boolean('requires_justificatif')->default(false);
            $table->boolean('requires_workflow')->default(true);
            $table->string('mode_calcul', 20)->default('calendaire');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_conges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_conge_id')->constrained('types_conges')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un seul modèle par défaut actif par type, garanti par la base.
            $table->unsignedBigInteger('defaut_type_id')
                ->virtualAs('CASE WHEN is_default = 1 AND is_active = 1 THEN type_conge_id ELSE NULL END')
                ->nullable()
                ->unique();
        });

        Schema::create('conges', function (Blueprint $table): void {
            $table->id();
            $table->string('matricule', 30)->index();
            $table->foreignId('type_conge_id')->constrained('types_conges')->restrictOnDelete();
            $table->foreignId('model_conge_id')->constrained('model_conges')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('nbre_jours', 5, 1)->default(0);
            $table->text('motif')->nullable();
            $table->string('reference', 60)->nullable()->unique();
            $table->longText('contenu_genere')->nullable();
            $table->unsignedInteger('version_modele')->nullable();
            $table->string('fichier_path')->nullable();
            $table->string('statut_generation', 20)->nullable()->index();
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conges');
        Schema::dropIfExists('model_conges');
        Schema::dropIfExists('types_conges');
    }
};
