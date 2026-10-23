<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_departement_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_departement_id')->constrained('site_departements')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'site_departement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_departement_user');
    }
};
