<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convites de times CPU para técnicos humanos demitidos (spec 005, RF-GES-03).
     * Expiram quando global_round da liga passa do global_round do convite.
     */
    public function up(): void
    {
        Schema::create('league_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('league_team_id')->constrained('league_teams')->cascadeOnDelete();

            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->unsignedInteger('global_round');

            $table->timestamps();

            $table->index(['league_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_invitations');
    }
};
