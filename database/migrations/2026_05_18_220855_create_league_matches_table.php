<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('league_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_championship_id')->constrained('league_championships')->cascadeOnDelete();
            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();

            $table->foreignUuid('home_team_id')->constrained('league_teams')->cascadeOnDelete();
            $table->foreignUuid('away_team_id')->constrained('league_teams')->cascadeOnDelete();

            // Rodada (pontos corridos) ou fase (mata-mata: 1=oitavas, 2=quartas, 3=semi, 4=final)
            $table->unsignedSmallInteger('round');

            // 1 = jogo único ou jogo de ida | 2 = jogo de volta
            $table->unsignedTinyInteger('leg')->default(1);

            $table->enum('status', [
                'scheduled',    // agendado
                'in_progress',  // em andamento
                'finished',     // encerrado
                'postponed',    // adiado
            ])->default('scheduled');

            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();

            // Preenchido ao encerrar partidas de mata-mata (single/double leg)
            $table->foreignUuid('winner_team_id')->nullable()->constrained('league_teams')->nullOnDelete();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            // Índices para queries frequentes
            $table->index(['league_championship_id', 'round']);
            $table->index(['league_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_matches');
    }
};
