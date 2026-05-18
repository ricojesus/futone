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
        Schema::table('league_teams', function (Blueprint $table) {
            // Satisfação atual do clube com o treinador (1–100); demissão quando < tolerance do time
            $table->unsignedTinyInteger('satisfaction')->default(50)->after('coach_id');
        });
    }

    public function down(): void
    {
        Schema::table('league_teams', function (Blueprint $table) {
            $table->dropColumn('satisfaction');
        });
    }
};
