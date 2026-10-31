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
            // Titre de congé / autorisation d'absence (type ANNUEL), figé à l'enregistrement.
            $table->string('qualification', 30)->nullable()->after('nbre_jours');
            // Autorisation d'absence prise durant les heures de travail (déduction conditionnelle).
            $table->boolean('pendant_heures_travail')->default(false)->after('qualification');
            // Permission exceptionnelle pour événement familial.
            $table->string('motif_permission', 40)->nullable()->after('pendant_heures_travail');
        });

        Schema::table('employes', function (Blueprint $table): void {
            // Société cliente (ex. SGTD) auprès de laquelle l'agent est mis à disposition.
            $table->foreignId('mis_a_disposition_societe_id')->nullable()->after('societe_id')
                ->constrained('societes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mis_a_disposition_societe_id');
        });

        Schema::table('conges', function (Blueprint $table): void {
            $table->dropColumn(['qualification', 'pendant_heures_travail', 'motif_permission']);
        });
    }
};
