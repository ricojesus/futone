<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa a satisfação em duas métricas:
 *   - satisfaction         → torcida com o clube (influencia público/renda de bilheteria)
 *   - coach_satisfaction   → clube com o técnico (dispara demissão; zera a cada troca de técnico)
 *
 * Antes deste ponto as duas coisas compartilhavam a mesma coluna, o que causava
 * um técnico recém-contratado poder ser demitido na rodada seguinte se o CPU
 * anterior tivesse deixado a satisfação baixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_teams', function (Blueprint $table) {
            $table->unsignedTinyInteger('coach_satisfaction')->default(50)->after('satisfaction');
        });

        // Novos técnicos partem de 50; clubes sem técnico humano herdam o valor atual.
        DB::table('league_teams')->update(['coach_satisfaction' => DB::raw('satisfaction')]);
    }

    public function down(): void
    {
        Schema::table('league_teams', function (Blueprint $table) {
            $table->dropColumn('coach_satisfaction');
        });
    }
};
