<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            // Fator de forma: 0.85–1.15, decai para 1.00 a cada rodada
            // e oscila conforme resultados do time
            $table->decimal('form_factor', 4, 2)->default(1.00)->after('wage_expectation_factor');
        });
    }

    public function down(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            $table->dropColumn('form_factor');
        });
    }
};
