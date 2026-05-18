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
        Schema::create('jogadores', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->enum('posicao', ['goleiro', 'defesa', 'meio', 'ataque']);
            $table->string('nacionalidade')->nullable();
            $table->unsignedTinyInteger('idade')->nullable();
            $table->unsignedTinyInteger('forca')->default(50)->comment('Atributo geral 1-99');
            $table->string('foto')->nullable()->comment('Caminho do arquivo no storage');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jogadores');
    }
};
