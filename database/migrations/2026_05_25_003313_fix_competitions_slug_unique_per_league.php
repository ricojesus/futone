<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O slug de uma competição só precisa ser único dentro de uma liga.
 * Troca o índice único global em slug por um índice composto (league_id, slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropUnique('competitions_slug_unique');
            $table->unique(['league_id', 'slug'], 'competitions_league_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropUnique('competitions_league_slug_unique');
            $table->unique('slug', 'competitions_slug_unique');
        });
    }
};
