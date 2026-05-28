<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            /**
             * Temporada inicial da liga (imutável).
             * Usado junto com max_seasons para saber quando encerrar automaticamente.
             * Ex: season_start=2026, max_seasons=3 → encerra após temporada 2028.
             */
            $table->unsignedSmallInteger('season_start')
                ->nullable()
                ->after('season');
        });

        // Retroativamente preenche ligas existentes
        \Illuminate\Support\Facades\DB::table('leagues')->update([
            'season_start' => \Illuminate\Support\Facades\DB::raw('season'),
        ]);
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('season_start');
        });
    }
};
