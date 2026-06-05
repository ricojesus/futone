<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pool de técnicos por liga.
 *
 * league_team_id = null  → técnico livre (mercado)
 * league_team_id = X     → técnico ativo gerenciando o time X
 *
 * Ao iniciar a liga, cada LeagueTeam recebe o técnico padrão do clube.
 * Quando um humano assume um time, o técnico vai para o mercado.
 * Quando um técnico é demitido, o próximo da fila de livres assume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_coaches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->foreignUuid('league_team_id')->nullable()->constrained('league_teams')->nullOnDelete();
            $table->timestamps();

            // Um técnico não pode estar em dois times da mesma liga
            $table->unique(['league_id', 'coach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_coaches');
    }
};
