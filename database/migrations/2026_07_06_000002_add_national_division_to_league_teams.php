<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A divisão nacional do clube passa a viver na liga (spec 002):
     * semeada do catálogo na criação do LeagueTeam e atualizada a cada
     * virada de temporada com promoções/rebaixamentos.
     */
    public function up(): void
    {
        Schema::table('league_teams', function (Blueprint $table) {
            $table->enum('national_division', ['first', 'second'])->nullable()->after('team_id');
        });

        // Backfill para ligas em andamento, a partir do catálogo mestre
        DB::statement("
            UPDATE league_teams lt
            JOIN teams t ON t.id = lt.team_id
            SET lt.national_division = t.national_division
            WHERE t.national_division IN ('first', 'second')
        ");
    }

    public function down(): void
    {
        Schema::table('league_teams', function (Blueprint $table) {
            $table->dropColumn('national_division');
        });
    }
};
