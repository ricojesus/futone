<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueTeam extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_id',
        'team_id',
        'user_id',
        'coach_id',
        'name',
        'satisfaction',
        'tolerance',
        'budget',
        'fans',
        'stadium_capacity',
        'ticket_price',
        'points',
        'wins',
        'draws',
        'losses',
        'goals_for',
        'goals_against',
    ];

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

    public function players(): HasMany
    {
        return $this->hasMany(LeaguePlayer::class, 'league_team_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeagueTransaction::class, 'league_team_id');
    }

    public function transferListings(): HasMany
    {
        return $this->hasMany(LeagueTransferListing::class, 'seller_team_id');
    }

    public function transferOffers(): HasMany
    {
        return $this->hasMany(LeagueTransferOffer::class, 'buyer_team_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(LeagueTransfer::class, 'to_team_id');
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(LeagueTransfer::class, 'from_team_id');
    }

    public function lineups(): HasMany
    {
        return $this->hasMany(LeagueLineup::class, 'league_team_id');
    }

    /**
     * Escalação ativa para uma rodada específica.
     * Retorna o override da rodada se existir, senão a escalação padrão (round=0).
     */
    public function activeLineup(int $round = 0): ?LeagueLineup
    {
        return $this->lineups()
            ->where('status', 'active')
            ->whereIn('round', [$round, 0])
            ->orderByDesc('round')   // prefere override de rodada ao padrão
            ->first();
    }

    public function isCpu(): bool
    {
        return $this->user_id === null;
    }

    public function goalDifference(): int
    {
        return $this->goals_for - $this->goals_against;
    }

    /** Demite o treinador se satisfaction < tolerance */
    public function shouldFireCoach(): bool
    {
        return $this->satisfaction < $this->tolerance;
    }
}
