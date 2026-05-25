<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_players', function (Blueprint $table) {
            $table->unsignedSmallInteger('goals_scored')->default(0)->after('fitness');
        });
    }

    public function down(): void
    {
        Schema::table('competition_players', function (Blueprint $table) {
            $table->dropColumn('goals_scored');
        });
    }
};
