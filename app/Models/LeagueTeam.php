<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueTeam extends Model
{
    use HasUuids;

    protected $table = 'league_teams';

    protected $fillable = [
        'league_id',
        'team_id',
        'user_id',
        'coach_id',
        'name',
        'budget',
        'fans',
        'stadium_capacity',
        'ticket_price',
        'tolerance',
        'satisfaction',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    /**
     * Stats pivot records — one per competition this team participates in.
     */
    public function competitionTeams(): HasMany
    {
        return $this->hasMany(CompetitionTeam::class, 'league_team_id');
    }

    /**
     * All players registered to this team across the league.
     */
    public function players(): HasMany
    {
        return $this->hasMany(CompetitionPlayer::class, 'league_team_id');
    }

    /**
     * All lineups for this team within the league.
     */
    public function lineups(): HasMany
    {
        return $this->hasMany(CompetitionLineup::class, 'league_team_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Returns true when the team is CPU-controlled (no human manager).
     */
    public function isCpu(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Active lineup for a given round.
     * Returns the round-specific override if it exists, otherwise the default (round = 0).
     */
    public function activeLineup(int $round = 0): ?CompetitionLineup
    {
        return $this->lineups()
            ->where('status', 'active')
            ->whereIn('round', [$round, 0])
            ->orderByDesc('round')
            ->first();
    }

    public function shouldFireCoach(): bool
    {
        return $this->satisfaction < $this->tolerance;
    }
}
