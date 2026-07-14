<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caixa de mensagens do Escritório do Técnico (spec 005).
     * Uma mensagem por evento relevante, endereçada a um usuário dentro de uma liga.
     */
    public function up(): void
    {
        Schema::create('league_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('league_team_id')->nullable()->constrained('league_teams')->nullOnDelete();

            // financial | transfer | match | lineup | club | invitation
            $table->string('type', 40);
            $table->string('title');
            $table->text('body');

            // Objeto de origem (proposta, partida, transação) — opcional
            $table->nullableUuidMorphs('subject');

            $table->unsignedInteger('global_round')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_messages');
    }
};
