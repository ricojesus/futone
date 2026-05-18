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
        Schema::create('league_championships', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();

            // Rastreabilidade ao catálogo (nullable: campeonato pode ser criado do zero)
            $table->foreignUuid('championship_id')->nullable()->constrained('championships')->nullOnDelete();

            // Snapshots imutáveis no momento da criação dentro da liga
            $table->string('name');
            $table->enum('type', ['league', 'cup', 'mixed'])->default('league');
            $table->enum('legs', ['single', 'double'])->default('double');
            $table->unsignedTinyInteger('teams_count');
            $table->unsignedTinyInteger('promotion_spots')->nullable();
            $table->unsignedTinyInteger('relegation_spots')->nullable();

            // Controle de progresso
            $table->enum('status', ['waiting', 'in_progress', 'finished'])->default('waiting');
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->unsignedSmallInteger('total_rounds')->default(0); // calculado ao iniciar

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_championships');
    }
};
