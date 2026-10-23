<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Format et préfixe de référence par module (modifiables par ADMIN / RH_MANAGER).
        Schema::create('reference_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 20)->unique();
            $table->string('prefixe', 40);
            $table->string('format', 60)->default('{numero:4}/{prefixe}/{annee}');
            $table->timestamps();
        });

        // Un compteur par module et par année, partagé par tous les types du module.
        Schema::create('reference_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 20);
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamps();

            $table->unique(['module', 'annee']);
        });

        // Workflow centralisé et polymorphe : une ligne par enregistrement métier.
        Schema::create('workflows', function (Blueprint $table): void {
            $table->id();
            $table->string('workflowable_type', 30);
            $table->unsignedBigInteger('workflowable_id');
            $table->string('statut', 30);
            $table->foreignId('cree_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('soumis_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approuve_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejete_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('echeance_at')->nullable();
            $table->timestamps();

            $table->unique(['workflowable_type', 'workflowable_id']);
            $table->index(['statut', 'echeance_at']);
        });

        // Historique en ajout seul : jamais modifié, jamais supprimé par l'application.
        Schema::create('workflow_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('de_statut', 30)->nullable();
            $table->string('vers_statut', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('commentaire')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workflow_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_histories');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('reference_sequences');
        Schema::dropIfExists('reference_modules');
    }
};
