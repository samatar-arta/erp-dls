<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employe_fichiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->string('categorie', 40)->index();
            $table->string('nom_original');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('taille')->default(0);
            $table->foreignId('depose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employe_fichiers');
    }
};
