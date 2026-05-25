<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Introduce `league_teams` as the single identity record for a team within a league.
 *
 * `competition_teams` becomes a stats-only pivot (one row per competition).
 * `competition_players` and `competition_lineups` now reference `league_teams`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Create league_teams ──────────────────────────────────────────

        Schema::create('league_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->nullOnDelete();

            // null = CPU-controlled team
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('coach_id')->nullable()->constrained('coaches')->nullOnDelete();

            $table->string('name');

            // Financial identity
            $table->unsignedBigInteger('budget')->default(0);
            $table->unsignedInteger('fans')->default(0);
            $table->unsignedInteger('stadium_capacity')->default(0);
            $table->unsignedBigInteger('ticket_price')->default(30);

            // Satisfaction
            $table->unsignedTinyInteger('tolerance')->default(30);
            $table->unsignedTinyInteger('satisfaction')->default(50);

            $table->timestamps();

            // A team can only appear once per league
            $table->unique(['league_id', 'team_id']);
        });

        // ── 2. Populate league_teams from competition_teams ─────────────────
        // One record per unique (league_id, team_id) combination.

        $rows = DB::table('competition_teams as ct')
            ->join('competitions as c', 'c.id', '=', 'ct.competition_id')
            ->select(
                'c.league_id',
                'ct.team_id',
                'ct.user_id',
                'ct.coach_id',
                'ct.name',
                'ct.budget',
                'ct.fans',
                'ct.stadium_capacity',
                'ct.ticket_price',
                'ct.tolerance',
                'ct.satisfaction',
                'ct.created_at',
                'ct.updated_at'
            )
            ->orderBy('ct.created_at')
            ->get();

        // De-duplicate: keep the first occurrence for each (league_id, team_id)
        $seen = [];
        $now  = now()->toDateTimeString();

        foreach ($rows as $row) {
            $key = $row->league_id . '|' . ($row->team_id ?? 'null_' . Str::random(8));

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            DB::table('league_teams')->insert([
                'id'               => (string) Str::uuid(),
                'league_id'        => $row->league_id,
                'team_id'          => $row->team_id,
                'user_id'          => $row->user_id,
                'coach_id'         => $row->coach_id,
                'name'             => $row->name,
                'budget'           => $row->budget,
                'fans'             => $row->fans,
                'stadium_capacity' => $row->stadium_capacity,
                'ticket_price'     => $row->ticket_price,
                'tolerance'        => $row->tolerance,
                'satisfaction'     => $row->satisfaction,
                'created_at'       => $row->created_at ?? $now,
                'updated_at'       => $row->updated_at ?? $now,
            ]);
        }

        // ── 3. Add league_team_id to competition_teams ──────────────────────

        Schema::table('competition_teams', function (Blueprint $table) {
            $table->uuid('league_team_id')->nullable()->after('id');
        });

        // Populate league_team_id via JOIN
        DB::statement('
            UPDATE competition_teams ct
            JOIN competitions c ON c.id = ct.competition_id
            JOIN league_teams lt ON lt.league_id = c.league_id
                AND lt.team_id <=> ct.team_id
            SET ct.league_team_id = lt.id
        ');

        // Make it NOT NULL and add FK
        Schema::table('competition_teams', function (Blueprint $table) {
            $table->uuid('league_team_id')->nullable(false)->change();
            $table->foreign('league_team_id')->references('id')->on('league_teams')->cascadeOnDelete();
            $table->unique(['competition_id', 'league_team_id'], 'comp_teams_comp_lt_unique');
        });

        // ── 4. Remove identity columns from competition_teams ───────────────

        Schema::table('competition_teams', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['coach_id']);
            $table->dropUnique(['competition_id', 'user_id']);
            $table->dropColumn(['user_id', 'coach_id', 'budget', 'fans', 'stadium_capacity', 'ticket_price', 'tolerance', 'satisfaction']);
        });

        // ── 5. competition_players: add league_team_id, drop competition_id ──

        Schema::table('competition_players', function (Blueprint $table) {
            $table->uuid('league_team_id')->nullable()->after('competition_team_id');
        });

        // Populate from competition_teams
        DB::statement('
            UPDATE competition_players cp
            JOIN competition_teams ct ON ct.id = cp.competition_team_id
            SET cp.league_team_id = ct.league_team_id
        ');

        // Make NOT NULL, add FK, drop old columns/FKs
        Schema::table('competition_players', function (Blueprint $table) {
            $table->uuid('league_team_id')->nullable(false)->change();
            $table->foreign('league_team_id')->references('id')->on('league_teams')->cascadeOnDelete();
        });

        // Drop unique constraint on (competition_id, player_id) — it no longer makes sense
        // and drop the competition_id and competition_team_id columns
        Schema::table('competition_players', function (Blueprint $table) {
            $table->dropForeign(['competition_id']);
            $table->dropForeign(['competition_team_id']);
            $table->dropUnique(['competition_id', 'player_id']);
            $table->dropColumn(['competition_id', 'competition_team_id']);
        });

        // Add unique per league_team + player
        Schema::table('competition_players', function (Blueprint $table) {
            $table->unique(['league_team_id', 'player_id'], 'comp_players_lt_player_unique');
        });

        // ── 6. competition_lineups: swap competition_team_id → league_team_id ─

        Schema::table('competition_lineups', function (Blueprint $table) {
            $table->uuid('league_team_id')->nullable()->after('competition_team_id');
        });

        DB::statement('
            UPDATE competition_lineups cl
            JOIN competition_teams ct ON ct.id = cl.competition_team_id
            SET cl.league_team_id = ct.league_team_id
        ');

        Schema::table('competition_lineups', function (Blueprint $table) {
            $table->uuid('league_team_id')->nullable(false)->change();
            $table->foreign('league_team_id')->references('id')->on('league_teams')->cascadeOnDelete();
        });

        Schema::table('competition_lineups', function (Blueprint $table) {
            // Drop FK first (it uses the unique index), then drop the unique index and column
            $table->dropForeign(['competition_team_id']);
        });

        Schema::table('competition_lineups', function (Blueprint $table) {
            $table->dropUnique(['competition_team_id', 'round', 'status']);
            $table->dropColumn('competition_team_id');
        });

        // competition_id on lineups becomes nullable (league-level default lineup)
        Schema::table('competition_lineups', function (Blueprint $table) {
            $table->uuid('competition_id')->nullable()->change();
            $table->unique(['league_team_id', 'round', 'status'], 'comp_lineups_lt_round_status_unique');
        });
    }

    public function down(): void
    {
        // ── Reverse competition_lineups ──────────────────────────────────────

        Schema::table('competition_lineups', function (Blueprint $table) {
            $table->dropUnique('comp_lineups_lt_round_status_unique');
            $table->uuid('competition_team_id')->nullable()->after('id');
        });

        // We cannot restore the original data, but add back the FK structure
        Schema::table('competition_lineups', function (Blueprint $table) {
            $table->foreign('competition_team_id')->references('id')->on('competition_teams')->cascadeOnDelete();
            $table->dropForeign(['league_team_id']);
            $table->dropColumn('league_team_id');
        });

        Schema::table('competition_lineups', function (Blueprint $table) {
            $table->unique(['competition_team_id', 'round', 'status']);
        });

        // ── Reverse competition_players ──────────────────────────────────────

        Schema::table('competition_players', function (Blueprint $table) {
            $table->dropUnique('comp_players_lt_player_unique');
            $table->dropForeign(['league_team_id']);
            $table->dropColumn('league_team_id');

            $table->foreignUuid('competition_id')->nullable()->constrained('competitions')->cascadeOnDelete();
            $table->foreignUuid('competition_team_id')->nullable()->constrained('competition_teams')->cascadeOnDelete();
        });

        // ── Reverse competition_teams ────────────────────────────────────────

        Schema::table('competition_teams', function (Blueprint $table) {
            $table->dropUnique('comp_teams_comp_lt_unique');
            $table->dropForeign(['league_team_id']);
            $table->dropColumn('league_team_id');

            // Restore identity columns
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('coach_id')->nullable()->constrained('coaches')->nullOnDelete();
            $table->unsignedBigInteger('budget')->default(0);
            $table->unsignedInteger('fans')->default(0);
            $table->unsignedInteger('stadium_capacity')->default(0);
            $table->unsignedBigInteger('ticket_price')->default(30);
            $table->unsignedTinyInteger('tolerance')->default(30);
            $table->unsignedTinyInteger('satisfaction')->default(50);
            $table->unique(['competition_id', 'user_id']);
        });

        // ── Drop league_teams ────────────────────────────────────────────────

        Schema::dropIfExists('league_teams');
    }
};
