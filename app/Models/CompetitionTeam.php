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

    // ── Relacionamentos ───────────────────────────────────────────────────

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id');
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
        return $this->hasMany(CompetitionPlayer::class, 'competition_team_id');
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

    public function lineups(): HasMany
    {
        return $this->hasMany(CompetitionLineup::class, 'competition_team_id');
    }

    /**
     * Escalação ativa para uma rodada específica.
     * Retorna o override da rodada se existir, senão a escalação padrão (round=0).
     */
    public function activeLineup(int $round = 0): ?CompetitionLineup
    {
        return $this->lineups()
            ->where('status', 'active')
            ->whereIn('round', [$round, 0])
            ->orderByDesc('round')
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

    public function shouldFireCoach(): bool
    {
        return $this->satisfaction < $this->tolerance;
    }
}
