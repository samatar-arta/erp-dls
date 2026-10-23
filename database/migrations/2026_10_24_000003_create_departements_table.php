<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departements', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('abrege', 30)->nullable();
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['societe_id', 'name']);
            // Cible des clés étrangères composites (departement_id, societe_id).
            $table->unique(['id', 'societe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departements');
    }
};
