<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O enum de competitions.competition_type ficou sem 'copa' quando o valor
     * foi adicionado a championships — CopaBrasilService::generate() falhava
     * (ou truncava) ao criar a Copa do Brasil.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE competitions
            MODIFY COLUMN competition_type
            ENUM('state','national','copa')
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM competitions WHERE competition_type = 'copa'");

        DB::statement("
            ALTER TABLE competitions
            MODIFY COLUMN competition_type
            ENUM('state','national')
            NOT NULL
        ");
    }
};
