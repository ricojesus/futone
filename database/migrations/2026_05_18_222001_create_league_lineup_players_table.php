<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_lineup_players', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('lineup_id')
                ->constrained('league_lineups')
                ->cascadeOnDelete();

            $table->foreignUuid('league_player_id')
                ->constrained('league_players')
                ->cascadeOnDelete();

            /**
             * Função do jogador nesta escalação.
             * Pode diferir da posição natural (ex: meia adiantado como atacante).
             * Usada pelo MatchSimulator para calcular o poder por setor.
             */
            $table->enum('role', ['goalkeeper', 'defender', 'midfielder', 'forward']);

            /**
             * true  = titular (entra em campo)
             * false = reserva (no banco, sem efeito na simulação por enquanto)
             */
            $table->boolean('is_starter')->default(true);

            /**
             * Ordem dentro do grupo posicional (1º DEF, 2º DEF…).
             * Usado para manter a ordenação consistente na UI.
             */
            $table->tinyInteger('slot')->default(1);

            $table->timestamps();

            // Cada jogador aparece uma única vez por escalação
            $table->unique(['lineup_id', 'league_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_lineup_players');
    }
};
