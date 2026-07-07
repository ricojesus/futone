<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O código (CompetitionPlayer::isFreeAgent, TransferService::signFreeAgent)
     * já usa status 'free_agent', mas o enum criado na reestruturação não o
     * incluía — qualquer escrita falhava com "data truncated".
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE competition_players
            MODIFY COLUMN status
            ENUM('active','injured','suspended','released','free_agent')
            NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        DB::statement("UPDATE competition_players SET status = 'released' WHERE status = 'free_agent'");

        DB::statement("
            ALTER TABLE competition_players
            MODIFY COLUMN status
            ENUM('active','injured','suspended','released')
            NOT NULL DEFAULT 'active'
        ");
    }
};
