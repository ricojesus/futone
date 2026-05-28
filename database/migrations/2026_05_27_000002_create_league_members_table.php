<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_members', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            /**
             * waiting  → aguardando sorteio do dono
             * assigned → já tem um time sorteado
             */
            $table->enum('status', ['waiting', 'assigned'])->default('waiting');

            $table->timestamps();

            $table->unique(['league_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_members');
    }
};
