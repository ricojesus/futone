<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            $table->unsignedBigInteger('wage')->default(0)->after('stamina');
            $table->unsignedBigInteger('market_value')->default(0)->after('wage');
            // Rodada em que o contrato expira (0 = sem contrato / free agent)
            $table->unsignedSmallInteger('contract_until')->default(0)->after('market_value');
            // Fator de expectativa salarial: 0.80–1.20, sorteado na criação
            $table->decimal('wage_expectation_factor', 3, 2)->default(1.00)->after('contract_until');
        });

        // Adiciona 'free_agent' ao enum de status
        DB::statement("
            ALTER TABLE league_players
            MODIFY COLUMN status
            ENUM('active','injured','suspended','released','free_agent')
            NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        Schema::table('league_players', function (Blueprint $table) {
            $table->dropColumn(['wage', 'market_value', 'contract_until', 'wage_expectation_factor']);
        });

        DB::statement("
            ALTER TABLE league_players
            MODIFY COLUMN status
            ENUM('active','injured','suspended','released')
            NOT NULL DEFAULT 'active'
        ");
    }
};
