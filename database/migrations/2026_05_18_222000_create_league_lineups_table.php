<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_lineups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')
                ->constrained('leagues')
                ->cascadeOnDelete();

            $table->foreignUuid('league_team_id')
                ->constrained('league_teams')
                ->cascadeOnDelete();

            /**
             * Formação tática — ex: "4-4-2", "4-3-3", "3-5-2".
             * Determina quantos jogadores por grupo posicional e
             * o modificador aplicado ao poder de cada setor do campo.
             */
            $table->string('formation', 10)->default('4-4-2');

            /**
             * Rodada à qual esta escalação se aplica.
             * 0 = escalação padrão (fallback para qualquer rodada sem override).
             * N = override específico para a rodada N.
             */
            $table->smallInteger('round')->default(0);

            $table->enum('status', ['active', 'draft'])->default('active');

            $table->timestamps();

            // Um time pode ter uma escalação ativa por rodada (0 = padrão)
            $table->unique(['league_team_id', 'round', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_lineups');
    }
};
