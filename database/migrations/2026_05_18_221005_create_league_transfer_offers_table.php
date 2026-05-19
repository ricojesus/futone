<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_transfer_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // null = oferta para free agent (sem listagem)
            $table->foreignUuid('listing_id')
                ->nullable()
                ->constrained('league_transfer_listings')
                ->cascadeOnDelete();

            $table->foreignUuid('buyer_team_id')->constrained('league_teams')->cascadeOnDelete();

            // Para contratação de free agent (sem listing)
            $table->foreignUuid('league_player_id')
                ->nullable()
                ->constrained('league_players')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('offered_fee');          // taxa ao clube vendedor
            $table->unsignedBigInteger('offered_wage');         // salário proposto ao jogador
            $table->unsignedTinyInteger('contract_rounds');     // duração do contrato

            $table->enum('status', [
                'pending',         // aguardando clube vendedor (humano)
                'pending_player',  // clube aceitou, aguardando jogador
                'accepted',        // ambos aceitaram — transferência em curso
                'rejected_team',   // clube vendedor recusou
                'rejected_player', // clube aceitou, jogador recusou os termos
                'countered',       // clube fez contraproposta
                'withdrawn',       // comprador desistiu
            ])->default('pending');

            // Contraproposta do clube vendedor
            $table->unsignedBigInteger('counter_price')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_team_id', 'status']);
            $table->index(['listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_transfer_offers');
    }
};
