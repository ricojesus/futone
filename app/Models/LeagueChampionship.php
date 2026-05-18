<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueChampionship extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_id',
        'championship_id',
        'name',
        'type',
        'legs',
        'teams_count',
        'promotion_spots',
        'relegation_spots',
        'status',
        'current_round',
        'total_rounds',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function championship(): BelongsTo
    {
        return $this->belongsTo(Championship::class, 'championship_id');
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}
