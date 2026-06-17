<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_matches', function (Blueprint $table) {
            $table->unsignedInteger('attendance')->nullable()->after('played_at');
            $table->unsignedBigInteger('match_revenue')->nullable()->after('attendance');
        });
    }

    public function down(): void
    {
        Schema::table('competition_matches', function (Blueprint $table) {
            $table->dropColumn(['attendance', 'match_revenue']);
        });
    }
};
