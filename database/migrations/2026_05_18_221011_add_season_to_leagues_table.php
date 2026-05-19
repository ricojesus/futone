<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            // Ano da temporada em curso dentro desta liga (ex: 2026, 2027…).
            // Definido pelo manager ao criar a liga e incrementado a cada virada de ano.
            // Permite calcular a idade atual dos jogadores e o histórico de temporadas.
            $table->unsignedSmallInteger('season')->default(2026)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('season');
        });
    }
};
