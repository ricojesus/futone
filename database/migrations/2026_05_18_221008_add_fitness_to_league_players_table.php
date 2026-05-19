<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            // Condição física atual (0–100). Cai após cada jogo, recupera no descanso.
            // Distinto de 'stamina' que é atributo inato (capacidade/velocidade de recuperação).
            $table->unsignedTinyInteger('fitness')->default(100)->after('stamina');

            // Rodada em que o jogador se recupera da lesão (null = saudável)
            $table->unsignedSmallInteger('injured_until')->nullable()->after('fitness');
        });
    }

    public function down(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            $table->dropColumn(['fitness', 'injured_until']);
        });
    }
};
