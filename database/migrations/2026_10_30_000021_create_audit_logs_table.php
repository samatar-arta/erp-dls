<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Instantané du nom de l'acteur : le journal reste lisible même si le compte évolue.
            $table->string('acteur')->nullable();
            $table->string('action', 60)->index();
            $table->string('cible_type', 60)->nullable();
            $table->string('cible_id', 64)->nullable();
            $table->string('description', 500);
            $table->json('donnees')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['cible_type', 'cible_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
