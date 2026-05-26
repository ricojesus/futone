<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE competition_matches MODIFY COLUMN status ENUM('scheduled','in_progress','halftime','finished','postponed') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE competition_matches MODIFY COLUMN status ENUM('scheduled','in_progress','finished','postponed') NOT NULL DEFAULT 'scheduled'");
    }
};
