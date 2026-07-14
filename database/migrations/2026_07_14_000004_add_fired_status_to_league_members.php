<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo usuário↔liga que sobrevive à demissão (spec 005).
     * O member 'fired' mantém a liga visível no dashboard do demitido e
     * guarda o contexto para o filtro de divisão e a carência do ex-clube.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE league_members
            MODIFY COLUMN status
            ENUM('waiting','assigned','fired')
            NOT NULL DEFAULT 'waiting'
        ");

        Schema::table('league_members', function (Blueprint $table) {
            $table->foreignUuid('fired_from_league_team_id')
                ->nullable()
                ->after('status')
                ->constrained('league_teams')
                ->nullOnDelete();
            $table->unsignedInteger('fired_at_global_round')->nullable()->after('fired_from_league_team_id');
        });
    }

    public function down(): void
    {
        Schema::table('league_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fired_from_league_team_id');
            $table->dropColumn('fired_at_global_round');
        });

        DB::statement("DELETE FROM league_members WHERE status = 'fired'");

        DB::statement("
            ALTER TABLE league_members
            MODIFY COLUMN status
            ENUM('waiting','assigned')
            NOT NULL DEFAULT 'waiting'
        ");
    }
};
