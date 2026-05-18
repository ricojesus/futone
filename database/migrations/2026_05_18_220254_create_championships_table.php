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
        Schema::create('championships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');

            // Escopo geográfico (ambos nullable = campeonato internacional)
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignUuid('state_id')->nullable()->constrained('states')->nullOnDelete();

            // Formato
            $table->enum('type', ['league', 'cup', 'mixed'])->default('league');
            // league = pontos corridos | cup = mata-mata | mixed = grupos + mata-mata

            $table->enum('legs', ['single', 'double'])->default('double');
            // single = jogo único | double = ida e volta

            $table->unsignedTinyInteger('teams_count')->default(20);

            // Acesso e rebaixamento (apenas para type=league)
            $table->unsignedTinyInteger('promotion_spots')->nullable();
            $table->unsignedTinyInteger('relegation_spots')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('championships');
    }
};
