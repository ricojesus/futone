<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueTransfer extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_id',
        'from_team_id',
        'to_team_id',
        'league_player_id',
        'fee',
        'wage',
        'contract_until',
        'round',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'to_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'league_player_id');
    }

    public function isFreeAgent(): bool
    {
        return $this->fee === 0 && is_null($this->from_team_id);
    }
}
