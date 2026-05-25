<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionTeam extends Model
{
    use HasUuids;

    protected $table = 'competition_teams';

    protected $fillable = [
        'competition_id',
        'league_team_id',
        'team_id',
        'name',
        'points',
        'wins',
        'draws',
        'losses',
        'goals_for',
        'goals_against',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function leagueTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'league_team_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CompetitionTransaction::class, 'competition_team_id');
    }

    public function transferListings(): HasMany
    {
        return $this->hasMany(CompetitionTransferListing::class, 'seller_team_id');
    }

    public function transferOffers(): HasMany
    {
        return $this->hasMany(CompetitionTransferOffer::class, 'buyer_team_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(CompetitionTransfer::class, 'to_team_id');
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(CompetitionTransfer::class, 'from_team_id');
    }

    // ── Proxies via leagueTeam ────────────────────────────────────────────

    /**
     * Players proxy — delegates to the LeagueTeam's players.
     */
    public function players(): HasMany
    {
        return $this->leagueTeam->players();
    }

    /**
     * Lineups proxy — delegates to the LeagueTeam's lineups.
     */
    public function lineups(): HasMany
    {
        return $this->leagueTeam->lineups();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isCpu(): bool
    {
        return $this->leagueTeam?->isCpu() ?? true;
    }

    public function goalDifference(): int
    {
        return $this->goals_for - $this->goals_against;
    }
}
