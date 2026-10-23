<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employe_affectations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites_affectation')->restrictOnDelete();
            $table->unsignedBigInteger('departement_id');
            $table->string('fonction')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('source', 30)->default('creation');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['departement_id', 'societe_id'])
                ->references(['id', 'societe_id'])->on('departements')
                ->restrictOnDelete();

            // Une seule ligne ouverte (date_fin NULL) par employé, garantie par la base.
            $table->unsignedBigInteger('employe_ouvert_id')
                ->virtualAs('CASE WHEN date_fin IS NULL THEN employe_id ELSE NULL END')
                ->nullable()
                ->unique();
            $table->index(['employe_id', 'date_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employe_affectations');
    }
};
