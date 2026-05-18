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
