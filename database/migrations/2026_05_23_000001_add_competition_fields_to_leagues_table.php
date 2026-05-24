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
             * Tipo da competição:
             *   state    → Campeonato Estadual
             *   national → Campeonato Brasileiro
             *
             * Nullable para backward-compat com ligas existentes (ligues de amigos).
             */
            $table->enum('competition_type', ['state', 'national'])
                ->nullable()
                ->after('slug');

            /**
             * Divisão dentro do tipo:
             *   first  → Série A / A1
             *   second → Série B / A2
             */
            $table->enum('division', ['first', 'second'])
                ->nullable()
                ->after('competition_type');
        });

        // Corrige capacidade do estádio em league_teams (SmallInt max=65535 < 72000)
        Schema::table('league_teams', function (Blueprint $table) {
            $table->unsignedMediumInteger('stadium_capacity')
                ->default(10_000)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['competition_type', 'division']);
        });

        Schema::table('league_teams', function (Blueprint $table) {
            $table->unsignedSmallInteger('stadium_capacity')
                ->default(10_000)
                ->change();
        });
    }
};
