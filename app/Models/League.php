<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasUuids;

    // ── Tipo de acesso ───────────────────────────────────────────────────
    const ACCESS_PUBLIC  = 'public';
    const ACCESS_PRIVATE = 'private';

    // ── Status ───────────────────────────────────────────────────────────
    const STATUS_WAITING     = 'waiting';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_FINISHED    = 'finished';
    const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'type',
        'invite_code',
        'status',
        'season',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class, 'league_id');
    }

    public function leagueTeams(): HasMany
    {
        return $this->hasMany(LeagueTeam::class, 'league_id');
    }

    // ── Helpers de status ────────────────────────────────────────────────

    public function isWaiting(): bool    { return $this->status === self::STATUS_WAITING; }
    public function isInProgress(): bool { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isFinished(): bool   { return $this->status === self::STATUS_FINISHED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }
}
