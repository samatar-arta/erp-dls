<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_model')->default(true);
            $table->boolean('requires_workflow')->default(true);
            $table->unsignedSmallInteger('delai_renouvellement_jours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_document_id')->constrained('types_documents')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un seul modèle par défaut actif par type, garanti par la base.
            $table->unsignedBigInteger('defaut_type_id')
                ->virtualAs('CASE WHEN is_default = 1 AND is_active = 1 THEN type_document_id ELSE NULL END')
                ->nullable()
                ->unique();
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->string('matricule', 30)->index();
            $table->foreignId('type_document_id')->constrained('types_documents')->restrictOnDelete();
            $table->foreignId('model_document_id')->nullable()->constrained('model_documents')->restrictOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_production')->nullable();
            $table->text('commentaire')->nullable();
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
        Schema::dropIfExists('documents');
        Schema::dropIfExists('model_documents');
        Schema::dropIfExists('types_documents');
    }
};
