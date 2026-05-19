<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();

            // null = contratação de free agent ou gerado pelo sistema
            $table->foreignUuid('from_team_id')
                ->nullable()
                ->constrained('league_teams')
                ->nullOnDelete();

            $table->foreignUuid('to_team_id')->constrained('league_teams')->cascadeOnDelete();
            $table->foreignUuid('league_player_id')->constrained('league_players')->cascadeOnDelete();

            $table->unsignedBigInteger('fee');            // 0 = free agent
            $table->unsignedBigInteger('wage');           // salário acordado por rodada
            $table->unsignedSmallInteger('contract_until'); // rodada de expiração

            $table->unsignedSmallInteger('round');        // rodada em que ocorreu
            $table->timestamp('transferred_at')->useCurrent();
            $table->timestamps();

            $table->index(['league_id', 'round']);
            $table->index('league_player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_transfers');
    }
};
