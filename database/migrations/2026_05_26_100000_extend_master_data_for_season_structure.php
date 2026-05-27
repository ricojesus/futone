<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── teams: overall + divisões + slug para logo ────────────────────
        Schema::table('teams', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->unsignedTinyInteger('overall')->default(70)->after('badge');
            // Em qual divisão do estadual este time joga (null = sem estadual)
            $table->enum('state_division', ['first', 'second'])->nullable()->after('overall');
            // Em qual divisão do brasileiro este time joga (null = fora do nacional)
            $table->enum('national_division', ['first', 'second'])->nullable()->after('state_division');
        });

        // ── players: vínculo com time mestre ─────────────────────────────
        Schema::table('players', function (Blueprint $table) {
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->nullOnDelete()->after('id');
        });

        // ── championships: divisão e tipo de competição ────────────────────
        Schema::table('championships', function (Blueprint $table) {
            // Tipo no contexto brasileiro: state, national, copa
            $table->enum('competition_type', ['state', 'national', 'copa'])
                  ->nullable()->after('state_id');
            // Divisão: first (A/A1/Série A), second (B/A2/Série B)
            $table->enum('division', ['first', 'second'])
                  ->nullable()->after('competition_type');
        });

        // ── leagues: fase atual da temporada ──────────────────────────────
        Schema::table('leagues', function (Blueprint $table) {
            $table->enum('current_phase', ['state', 'copa', 'national'])
                  ->default('state')->after('season');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('current_phase');
        });

        Schema::table('championships', function (Blueprint $table) {
            $table->dropColumn(['competition_type', 'division']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'overall', 'state_division', 'national_division']);
        });
    }
};
