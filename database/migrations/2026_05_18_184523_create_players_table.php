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
        Schema::dropIfExists('jogadores');

        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('position', ['goalkeeper', 'defender', 'midfielder', 'forward']);
            $table->string('nationality')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->unsignedTinyInteger('strength')->default(50)->comment('Overall 1-99');
            $table->unsignedTinyInteger('stamina')->default(100)->comment('Stamina 1-100');
            $table->string('photo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
