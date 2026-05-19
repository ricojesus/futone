<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Base de torcedores do clube (snapshot ao entrar numa liga)
            $table->unsignedInteger('fans_base')->default(10_000)->after('tolerance');
            // Capacidade do estádio (snapshot ao entrar numa liga)
            $table->unsignedSmallInteger('stadium_capacity')->default(10_000)->after('fans_base');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['fans_base', 'stadium_capacity']);
        });
    }
};
