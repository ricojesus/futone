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
        Schema::table('leagues', function (Blueprint $table) {
            // Liga vinculada a uma cidade específica (nullable = campeonato nacional/aberto).
            // Usado para filtrar times elegíveis em campeonatos municipais ou estaduais.
            $table->foreignUuid('city_id')->nullable()->after('owner_id')->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
