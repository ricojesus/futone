<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_teams', function (Blueprint $table) {
            // Snapshots do catálogo no momento da inscrição
            $table->unsignedInteger('fans')->default(10_000)->after('budget');
            $table->unsignedSmallInteger('stadium_capacity')->default(10_000)->after('fans');
            // Preço do ingresso definido pelo manager (padrão: 40)
            $table->unsignedSmallInteger('ticket_price')->default(40)->after('stadium_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('league_teams', function (Blueprint $table) {
            $table->dropColumn(['fans', 'stadium_capacity', 'ticket_price']);
        });
    }
};
