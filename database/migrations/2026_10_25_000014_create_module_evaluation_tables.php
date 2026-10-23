<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_evaluation_id')->constrained('types_evaluations')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            $table->json('criteres')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un seul modèle par défaut actif par type, garanti par la base.
            $table->unsignedBigInteger('defaut_type_id')
                ->virtualAs('CASE WHEN is_default = 1 AND is_active = 1 THEN type_evaluation_id ELSE NULL END')
                ->nullable()
                ->unique();
        });

        Schema::create('evaluations', function (Blueprint $table): void {
            $table->id();
            $table->string('matricule', 30)->index();
            $table->foreignId('type_evaluation_id')->constrained('types_evaluations')->restrictOnDelete();
            $table->foreignId('model_evaluation_id')->constrained('model_evaluations')->restrictOnDelete();
            $table->foreignId('evaluateur_id')->constrained('users')->restrictOnDelete();
            $table->date('date_evaluation');
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->json('notes_criteres')->nullable();
            $table->decimal('note', 5, 2)->nullable();
            $table->text('appreciation')->nullable();
            $table->text('objectifs')->nullable();
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
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('model_evaluations');
        Schema::dropIfExists('types_evaluations');
    }
};
