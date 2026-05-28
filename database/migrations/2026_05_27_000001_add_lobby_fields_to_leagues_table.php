<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            /**
             * Define como os times são distribuídos entre os managers.
             *   manual → cada jogador escolhe livremente um time disponível
             *   auto   → o dono sorteia os times para todos de uma vez (lobby)
             */
            $table->enum('team_assignment', ['manual', 'auto'])
                ->default('manual')
                ->after('current_phase');

            /**
             * Número máximo de temporadas da liga.
             * null = indefinido (dono decide quando encerrar).
             */
            $table->unsignedTinyInteger('max_seasons')
                ->nullable()
                ->after('team_assignment');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['team_assignment', 'max_seasons']);
        });
    }
};
