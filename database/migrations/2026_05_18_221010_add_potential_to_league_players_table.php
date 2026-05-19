<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            // Snapshot do potencial no catálogo no momento da entrada na liga
            $table->unsignedTinyInteger('potential')->default(75)->after('stamina');
        });
    }

    public function down(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            $table->dropColumn('potential');
        });
    }
};
