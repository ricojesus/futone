<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_matches', function (Blueprint $table) {
            // Slot no chaveamento (ex: 1–32 na R64, 1–16 na R32, etc.)
            // null = competições de pontos corridos
            $table->unsignedSmallInteger('bracket_slot')->nullable()->after('leg');
        });

        Schema::table('competitions', function (Blueprint $table) {
            // Dados do bracket (JSON): seeding, slots por rodada
            $table->json('bracket_data')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('bracket_data');
        });
        Schema::table('competition_matches', function (Blueprint $table) {
            $table->dropColumn('bracket_slot');
        });
    }
};
