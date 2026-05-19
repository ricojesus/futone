<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_team_id')->constrained('league_teams')->cascadeOnDelete();

            $table->enum('type', [
                'wage_payment',    // salário de jogador ou treinador
                'transfer_fee_in', // recebeu pela venda de jogador
                'transfer_fee_out',// pagou pela compra de jogador
                'match_revenue',   // bilheteria de partida em casa
                'prize_money',     // premiação por colocação
                'sponsorship',     // patrocínio fixo por temporada
                'other_credit',
                'other_debit',
            ]);

            // Positivo = crédito, negativo = débito
            $table->bigInteger('amount');

            $table->string('description')->nullable();

            // Rodada que originou a transação
            $table->unsignedSmallInteger('round')->nullable();

            $table->timestamps();

            $table->index(['league_team_id', 'type']);
            $table->index(['league_team_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_transactions');
    }
};
