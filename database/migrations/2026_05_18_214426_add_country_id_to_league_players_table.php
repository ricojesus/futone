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
        Schema::table('league_players', function (Blueprint $table) {
            // Snapshot da nacionalidade no momento da entrada na liga.
            // Protege o dado histórico caso o jogador seja removido do catálogo (nullOnDelete em player_id).
            $table->foreignUuid('country_id')->nullable()->after('player_id')->constrained('countries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
