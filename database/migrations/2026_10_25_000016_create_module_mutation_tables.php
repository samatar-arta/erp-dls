<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_mutations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_mutations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_mutation_id')->constrained('types_mutations')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un seul modèle par défaut actif par type, garanti par la base.
            $table->unsignedBigInteger('defaut_type_id')
                ->virtualAs('CASE WHEN is_default = 1 AND is_active = 1 THEN type_mutation_id ELSE NULL END')
                ->nullable()
                ->unique();
        });

        Schema::create('mutations', function (Blueprint $table): void {
            $table->id();
            $table->string('matricule', 30)->index();
            $table->foreignId('type_mutation_id')->constrained('types_mutations')->restrictOnDelete();
            $table->foreignId('model_mutation_id')->constrained('model_mutations')->restrictOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ancien_site_id')->constrained('sites_affectation')->restrictOnDelete();
            $table->foreignId('nouveau_site_id')->nullable()->constrained('sites_affectation')->restrictOnDelete();
            $table->unsignedBigInteger('ancien_departement_id');
            $table->unsignedBigInteger('nouveau_departement_id')->nullable();
            $table->foreignId('ancienne_societe_id')->constrained('societes')->restrictOnDelete();
            $table->foreignId('nouvelle_societe_id')->nullable()->constrained('societes')->restrictOnDelete();
            $table->string('ancienne_fonction')->nullable();
            $table->string('nouvelle_fonction')->nullable();
            $table->date('date_effet');
            $table->text('motif')->nullable();
            $table->string('reference', 60)->nullable()->unique();
            $table->longText('contenu_genere')->nullable();
            $table->unsignedInteger('version_modele')->nullable();
            $table->string('fichier_path')->nullable();
            $table->string('statut_generation', 20)->nullable()->index();
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['ancien_departement_id', 'ancienne_societe_id'])->references(['id', 'societe_id'])->on('departements')->restrictOnDelete();
            $table->foreign(['nouveau_departement_id', 'nouvelle_societe_id'])->references(['id', 'societe_id'])->on('departements')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutations');
        Schema::dropIfExists('model_mutations');
        Schema::dropIfExists('types_mutations');
    }
};
