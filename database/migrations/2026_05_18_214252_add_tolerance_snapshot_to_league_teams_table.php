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
            // Snapshot da tolerância no momento da entrada na liga.
            // Isola a lógica de demissão de edições posteriores no catálogo de times.
            $table->unsignedTinyInteger('tolerance')->default(50)->after('satisfaction');
        });
    }

    public function down(): void
    {
        Schema::table('league_teams', function (Blueprint $table) {
            $table->dropColumn('tolerance');
        });
    }
};
