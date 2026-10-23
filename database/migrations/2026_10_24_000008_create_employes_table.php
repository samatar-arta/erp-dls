<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employes', function (Blueprint $table): void {
            $table->id();
            $table->string('matricule', 30)->unique();
            $table->string('name');
            $table->string('fonction')->nullable();
            $table->date('date_naissance')->nullable();
            $table->date('date_embauche');
            $table->foreignId('type_contrat_id')->nullable()->constrained('types_contrats')->restrictOnDelete();
            $table->string('categorie_employe')->nullable();
            $table->string('statut_employe', 30)->default('actif')->index();
            $table->date('date_sortie')->nullable();
            $table->string('motif_sortie')->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('num_CNSS', 40)->nullable();
            $table->string('num_CNI', 40)->nullable();
            $table->string('niveau_etude')->nullable();
            $table->string('adresse')->nullable();
            $table->string('situation_familiale')->nullable();
            $table->string('personne_contacter')->nullable();
            $table->string('lien_parental')->nullable();
            $table->string('telephone_parental', 40)->nullable();
            $table->text('formation_certificat')->nullable();
            $table->string('permis_conduite')->nullable();
            $table->string('categorie')->nullable();
            $table->boolean('handicap')->default(false);
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->unsignedBigInteger('departement_id');
            $table->foreignId('site_id')->constrained('sites_affectation')->restrictOnDelete();
            $table->string('dossier_path')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['departement_id', 'societe_id'])
                ->references(['id', 'societe_id'])->on('departements')
                ->restrictOnDelete();
            $table->index(['site_id', 'departement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employes');
    }
};
