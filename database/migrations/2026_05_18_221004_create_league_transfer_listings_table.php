<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_transfer_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('seller_team_id')->constrained('league_teams')->cascadeOnDelete();
            $table->foreignUuid('league_player_id')->constrained('league_players')->cascadeOnDelete();

            $table->unsignedBigInteger('asking_price');
            // Preço mínimo aceitável (privado — só o sistema compara)
            $table->unsignedBigInteger('min_acceptable');

            $table->enum('status', ['open', 'sold', 'withdrawn'])->default('open');

            $table->timestamp('listed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'status']);
            // Um jogador só pode ter uma listagem aberta por vez
            $table->unique(['league_player_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_transfer_listings');
    }
};
