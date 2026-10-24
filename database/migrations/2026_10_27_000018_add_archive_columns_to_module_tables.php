<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['documents', 'conges', 'sanctions', 'evaluations', 'promotions', 'mutations'];

    public function up(): void
    {
        foreach ($this->tables as $nom) {
            Schema::table($nom, function (Blueprint $table): void {
                // Archivage : empreinte SHA-256 du PDF généré (contrôle d'intégrité au téléchargement).
                $table->char('empreinte_pdf', 64)->nullable()->after('fichier_path');
                $table->timestamp('genere_le')->nullable()->after('statut_generation');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $nom) {
            Schema::table($nom, function (Blueprint $table): void {
                $table->dropColumn(['empreinte_pdf', 'genere_le']);
            });
        }
    }
};
