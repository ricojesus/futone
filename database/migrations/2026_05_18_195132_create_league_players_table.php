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
        Schema::create('league_players', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('league_team_id')->constrained('league_teams')->cascadeOnDelete();

            // Rastreabilidade ao catálogo — nullable: jogador pode não existir no catálogo
            $table->foreignUuid('player_id')->nullable()->constrained('players')->nullOnDelete();

            // Snapshot imutável (protege histórico se o catálogo mudar)
            $table->string('name');
            $table->enum('position', ['goalkeeper', 'defender', 'midfielder', 'forward']);

            // Atributos que evoluem de forma independente dentro da liga
            $table->unsignedTinyInteger('age');
            $table->unsignedTinyInteger('strength');
            $table->unsignedTinyInteger('stamina');

            $table->enum('status', [
                'active',
                'injured',
                'suspended',
                'released',   // dispensado do elenco
            ])->default('active');

            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            // Um jogador do catálogo só pode estar uma vez por liga (em qualquer elenco)
            $table->unique(['league_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_players');
    }
};
