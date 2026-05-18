<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaguePlayer extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_id',
        'league_team_id',
        'player_id',
        'country_id',
        'name',
        'position',
        'age',
        'strength',
        'stamina',
        'status',
        'joined_at',
        'released_at',
    ];

    protected $casts = [
        'joined_at'   => 'datetime',
        'released_at' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function leagueTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'league_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'active';
    }

    public function positionLabel(): string
    {
        return Player::$positions[$this->position] ?? ucfirst($this->position);
    }
}
