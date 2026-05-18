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
        Schema::create('leagues', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();               // URL amigável

            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', ['public', 'private'])->default('public');
            $table->string('invite_code', 12)->nullable()->unique(); // só quando private

            $table->unsignedTinyInteger('max_teams')->default(8);   // 4, 8, 16, 32

            $table->enum('status', [
                'waiting',      // aguardando participantes
                'in_progress',  // rodadas em andamento
                'finished',     // encerrada
                'cancelled',    // cancelada pelo dono
            ])->default('waiting');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
