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

    // ── Fases da temporada ───────────────────────────────────────────────
    const PHASE_STATE    = 'state';
    const PHASE_COPA     = 'copa';
    const PHASE_NATIONAL = 'national';

    // ── Atribuição de times ──────────────────────────────────────────────
    const ASSIGNMENT_MANUAL = 'manual';
    const ASSIGNMENT_AUTO   = 'auto';

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'type',
        'invite_code',
        'status',
        'season',
        'season_start',
        'current_phase',
        'global_round',
        'team_assignment',
        'max_seasons',
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

    public function members(): HasMany
    {
        return $this->hasMany(LeagueMember::class, 'league_id');
    }

    // ── Helpers de status ────────────────────────────────────────────────

    public function isWaiting(): bool    { return $this->status === self::STATUS_WAITING; }
    public function isInProgress(): bool { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isFinished(): bool   { return $this->status === self::STATUS_FINISHED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }

    // ── Helpers de atribuição ────────────────────────────────────────────

    public function isAutoAssignment(): bool   { return $this->team_assignment === self::ASSIGNMENT_AUTO; }
    public function isManualAssignment(): bool { return $this->team_assignment === self::ASSIGNMENT_MANUAL; }

    // ── Helpers de temporada ─────────────────────────────────────────────

    public function hasSeasonLimit(): bool { return ! is_null($this->max_seasons); }

    public function isLastSeason(): bool
    {
        if (! $this->hasSeasonLimit()) return false;
        $start   = (int) ($this->season_start ?? $this->season);
        $elapsed = ($this->season - $start) + 1;
        return $elapsed >= $this->max_seasons;
    }

    public function seasonLabel(): string
    {
        if (! $this->hasSeasonLimit()) return "Temporada {$this->season}";
        $start   = (int) ($this->season_start ?? $this->season);
        $current = ($this->season - $start) + 1;
        return "Temporada {$current}/{$this->max_seasons}";
    }
}
