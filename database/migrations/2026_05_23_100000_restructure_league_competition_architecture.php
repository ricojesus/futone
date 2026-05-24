<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure: League = world container, Competition = individual championship.
 *
 * Drops all old league_* tables and recreates the full schema with the new names.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Drop dependents in reverse FK order ────────────────────────

        Schema::dropIfExists('league_lineup_players');
        Schema::dropIfExists('league_lineups');
        Schema::dropIfExists('league_transfers');
        Schema::dropIfExists('league_transfer_offers');
        Schema::dropIfExists('league_transfer_listings');
        Schema::dropIfExists('league_transactions');
        Schema::dropIfExists('league_matches');
        Schema::dropIfExists('league_championships');
        Schema::dropIfExists('league_players');
        Schema::dropIfExists('league_teams');
        Schema::dropIfExists('leagues');

        // ── 2. leagues (world container) ────────────────────────────────────

        Schema::create('leagues', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();

            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', ['public', 'private'])->default('public');
            $table->string('invite_code', 12)->nullable()->unique();

            $table->enum('status', [
                'waiting',
                'in_progress',
                'finished',
                'cancelled',
            ])->default('waiting');

            $table->unsignedSmallInteger('season')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // ── 3. competitions (individual championships inside a league) ────

        Schema::create('competitions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();

            // Rastreabilidade ao catálogo
            $table->foreignUuid('championship_id')->nullable()->constrained('championships')->nullOnDelete();

            $table->string('name', 150);
            $table->string('slug', 160)->unique();

            $table->enum('competition_type', ['state', 'national']);
            $table->enum('division', ['first', 'second']);

            // Null for national competitions
            $table->foreignUuid('state_id')->nullable()->constrained('states')->nullOnDelete();

            $table->enum('format', ['league', 'cup', 'mixed'])->default('league');
            $table->enum('legs', ['single', 'double'])->default('double');

            $table->unsignedSmallInteger('teams_count');
            $table->unsignedTinyInteger('promotion_spots')->nullable();
            $table->unsignedTinyInteger('relegation_spots')->nullable();

            $table->enum('status', ['waiting', 'in_progress', 'finished', 'cancelled'])->default('waiting');
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->unsignedSmallInteger('total_rounds')->nullable();

            $table->unsignedSmallInteger('season')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'status']);
        });

        // ── 4. competition_teams ──────────────────────────────────────────

        Schema::create('competition_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('competition_id')->constrained('competitions')->cascadeOnDelete();

            $table->foreignUuid('team_id')->nullable()->constrained('teams')->nullOnDelete();

            // null = CPU-controlled team
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignUuid('coach_id')->nullable()->constrained('coaches')->nullOnDelete();

            $table->string('name');

            // Financial
            $table->unsignedBigInteger('budget')->default(0);
            $table->unsignedBigInteger('ticket_price')->default(30);
            $table->unsignedInteger('fans')->default(0);
            $table->unsignedInteger('stadium_capacity')->default(0);

            // Satisfaction
            $table->unsignedTinyInteger('satisfaction')->default(50);
            $table->unsignedTinyInteger('tolerance')->default(30);

            // Standings (updated each round)
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('draws')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);

            $table->timestamps();

            // A catalog team can only appear once per competition
            $table->unique(['competition_id', 'team_id']);
            // A user can only control one team per competition
            $table->unique(['competition_id', 'user_id']);
        });

        // ── 5. competition_players ────────────────────────────────────────

        Schema::create('competition_players', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignUuid('competition_team_id')->constrained('competition_teams')->cascadeOnDelete();

            $table->foreignUuid('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();

            // Immutable snapshot
            $table->string('name');
            $table->enum('position', ['goalkeeper', 'defender', 'midfielder', 'forward']);

            // Attributes that evolve independently within the competition
            $table->unsignedTinyInteger('age');
            $table->unsignedTinyInteger('strength');
            $table->unsignedTinyInteger('stamina');
            $table->unsignedTinyInteger('potential')->default(50);

            $table->decimal('form_factor', 4, 2)->default(1.00);
            $table->unsignedTinyInteger('fitness')->default(100);

            $table->enum('status', [
                'active',
                'injured',
                'suspended',
                'released',
            ])->default('active');

            $table->unsignedBigInteger('wage')->default(0);
            $table->unsignedBigInteger('market_value')->default(0);
            $table->unsignedSmallInteger('contract_until')->nullable();
            $table->decimal('wage_expectation_factor', 4, 2)->default(1.00);

            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('injured_until')->nullable();

            $table->timestamps();

            // A catalog player can only be in one team per competition
            $table->unique(['competition_id', 'player_id']);
        });

        // ── 6. competition_matches ────────────────────────────────────────

        Schema::create('competition_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('competition_id')->constrained('competitions')->cascadeOnDelete();

            $table->foreignUuid('home_team_id')->constrained('competition_teams')->cascadeOnDelete();
            $table->foreignUuid('away_team_id')->constrained('competition_teams')->cascadeOnDelete();

            $table->unsignedSmallInteger('round');
            $table->unsignedTinyInteger('leg')->default(1);

            $table->enum('status', [
                'scheduled',
                'in_progress',
                'finished',
                'postponed',
            ])->default('scheduled');

            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();

            $table->foreignUuid('winner_team_id')->nullable()->constrained('competition_teams')->nullOnDelete();

            $table->json('data')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'round']);
            $table->index(['competition_id', 'status']);
        });

        // ── 7. competition_lineups ────────────────────────────────────────

        Schema::create('competition_lineups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignUuid('competition_team_id')->constrained('competition_teams')->cascadeOnDelete();

            $table->string('formation', 10)->default('4-4-2');

            // 0 = default lineup (fallback), N = round-specific override
            $table->smallInteger('round')->default(0);

            $table->enum('status', ['active', 'draft'])->default('active');

            $table->timestamps();

            $table->unique(['competition_team_id', 'round', 'status']);
        });

        // ── 8. competition_lineup_players ─────────────────────────────────

        Schema::create('competition_lineup_players', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('lineup_id')->constrained('competition_lineups')->cascadeOnDelete();
            $table->foreignUuid('competition_player_id')->constrained('competition_players')->cascadeOnDelete();

            $table->enum('role', ['goalkeeper', 'defender', 'midfielder', 'forward']);
            $table->boolean('is_starter')->default(true);
            $table->tinyInteger('slot')->default(1);

            $table->timestamps();

            $table->unique(['lineup_id', 'competition_player_id'], 'comp_lineup_players_unique');
        });

        // ── 9. Financial tables ───────────────────────────────────────────

        Schema::create('competition_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('competition_team_id')->constrained('competition_teams')->cascadeOnDelete();

            $table->enum('type', [
                'wage_payment',
                'transfer_fee_in',
                'transfer_fee_out',
                'match_revenue',
                'prize_money',
                'sponsorship',
                'other_credit',
                'other_debit',
            ]);

            $table->bigInteger('amount');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('round')->nullable();

            $table->timestamps();

            $table->index(['competition_team_id', 'type']);
            $table->index(['competition_team_id', 'round']);
        });

        Schema::create('competition_transfer_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignUuid('seller_team_id')->constrained('competition_teams')->cascadeOnDelete();
            $table->foreignUuid('competition_player_id')->constrained('competition_players')->cascadeOnDelete();

            $table->unsignedBigInteger('asking_price');
            $table->unsignedBigInteger('min_acceptable');

            $table->enum('status', ['open', 'sold', 'withdrawn'])->default('open');

            $table->timestamp('listed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'status']);
            $table->unique(['competition_player_id', 'status'], 'comp_transfer_listings_player_status_unique');
        });

        Schema::create('competition_transfer_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('listing_id')
                ->nullable()
                ->constrained('competition_transfer_listings')
                ->cascadeOnDelete();

            $table->foreignUuid('buyer_team_id')->constrained('competition_teams')->cascadeOnDelete();

            $table->foreignUuid('competition_player_id')
                ->nullable()
                ->constrained('competition_players')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('offered_fee');
            $table->unsignedBigInteger('offered_wage');
            $table->unsignedTinyInteger('contract_rounds');

            $table->enum('status', [
                'pending',
                'pending_player',
                'accepted',
                'rejected_team',
                'rejected_player',
                'countered',
                'withdrawn',
            ])->default('pending');

            $table->unsignedBigInteger('counter_price')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_team_id', 'status']);
            $table->index(['listing_id', 'status']);
        });

        Schema::create('competition_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('competition_id')->constrained('competitions')->cascadeOnDelete();

            $table->foreignUuid('from_team_id')
                ->nullable()
                ->constrained('competition_teams')
                ->nullOnDelete();

            $table->foreignUuid('to_team_id')->constrained('competition_teams')->cascadeOnDelete();
            $table->foreignUuid('competition_player_id')->constrained('competition_players')->cascadeOnDelete();

            $table->unsignedBigInteger('fee');
            $table->unsignedBigInteger('wage');
            $table->unsignedSmallInteger('contract_until');

            $table->unsignedSmallInteger('round');
            $table->timestamp('transferred_at')->useCurrent();
            $table->timestamps();

            $table->index(['competition_id', 'round']);
            $table->index('competition_player_id');
        });
    }

    public function down(): void
    {
        // Drop new tables in reverse FK order
        Schema::dropIfExists('competition_transfers');
        Schema::dropIfExists('competition_transfer_offers');
        Schema::dropIfExists('competition_transfer_listings');
        Schema::dropIfExists('competition_transactions');
        Schema::dropIfExists('competition_lineup_players');
        Schema::dropIfExists('competition_lineups');
        Schema::dropIfExists('competition_matches');
        Schema::dropIfExists('competition_players');
        Schema::dropIfExists('competition_teams');
        Schema::dropIfExists('competitions');
        Schema::dropIfExists('leagues');

        // Restore old leagues table (minimal, for rollback)
        Schema::create('leagues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['public', 'private'])->default('public');
            $table->string('invite_code', 12)->nullable()->unique();
            $table->unsignedTinyInteger('max_teams')->default(8);
            $table->enum('status', ['waiting', 'in_progress', 'finished', 'cancelled'])->default('waiting');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
};
