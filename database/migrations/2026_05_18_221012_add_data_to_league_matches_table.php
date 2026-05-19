<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_matches', function (Blueprint $table) {
            // Estatísticas e eventos da partida em JSON
            // { home_possession, away_possession, home_shots, away_shots,
            //   home_shots_on_target, away_shots_on_target,
            //   events: [{type, team, play}] }
            $table->json('data')->nullable()->after('played_at');
        });
    }

    public function down(): void
    {
        Schema::table('league_matches', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
