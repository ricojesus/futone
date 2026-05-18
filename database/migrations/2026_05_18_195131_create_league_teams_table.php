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
        Schema::create('league_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();

            // Referência ao catálogo — nullable: time pode ser criado do zero (CPU ou custom)
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->nullOnDelete();

            // null = time controlado por CPU
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Treinador desta equipe nesta liga
            $table->foreignUuid('coach_id')->nullable()->constrained('coaches')->nullOnDelete();

            // Snapshot do nome no momento da entrada (imune a edições no catálogo)
            $table->string('name');

            // Financeiro
            $table->unsignedBigInteger('budget')->default(0);

            // Classificação (atualizada a cada rodada)
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('draws')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);

            $table->timestamps();

            // Um time do catálogo só pode entrar uma vez por liga
            $table->unique(['league_id', 'team_id']);
            // Um usuário só pode controlar um time por liga
            $table->unique(['league_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_teams');
    }
};
