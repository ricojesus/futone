<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMember extends Model
{
    use HasUuids;

    const STATUS_WAITING  = 'waiting';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_FIRED    = 'fired';

    protected $fillable = [
        'league_id',
        'user_id',
        'status',
        'fired_from_league_team_id',
        'fired_at_global_round',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }
}
