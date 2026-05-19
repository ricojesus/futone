<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            /**
             * Define como os times são distribuídos entre os managers.
             *
             * 'choice' → cada manager escolhe livremente um time disponível.
             * 'random' → o sistema sorteia um time aleatório ao entrar na liga.
             *
             * Definido pelo criador da liga e imutável após o início.
             */
            $table->enum('team_assignment', ['choice', 'random'])
                ->default('choice')
                ->after('max_teams');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('team_assignment');
        });
    }
};
