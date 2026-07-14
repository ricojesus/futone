<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contador monotônico de rodadas globais da liga (spec 005).
     * Incrementado a cada GlobalRoundService::advance; é a unidade de tempo
     * de mensagens, expiração de convites e carência do ex-clube.
     */
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->unsignedInteger('global_round')->default(0)->after('current_phase');
        });

        // Backfill aproximado para ligas em andamento: maior rodada entre as competições
        DB::statement("
            UPDATE leagues l
            SET global_round = COALESCE(
                (SELECT MAX(c.current_round) FROM competitions c WHERE c.league_id = l.id),
                0
            )
        ");
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('global_round');
        });
    }
};
