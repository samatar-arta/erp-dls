<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['promotions', 'mutations'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                // Date à laquelle l'effet sur l'affectation de l'agent a été appliqué (null = pas encore).
                $t->timestamp('appliquee_le')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['promotions', 'mutations'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->dropIndex([ 'appliquee_le' ]);
                $t->dropColumn('appliquee_le');
            });
        }
    }
};
