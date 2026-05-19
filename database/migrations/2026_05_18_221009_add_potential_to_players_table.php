<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Teto de crescimento de atributos (1–100).
            // strength e stamina nunca ultrapassam este valor via desenvolvimento.
            // Definido pelo admin no catálogo. Default 75 = jogador mediano.
            $table->unsignedTinyInteger('potential')->default(75)->after('stamina');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('potential');
        });
    }
};
