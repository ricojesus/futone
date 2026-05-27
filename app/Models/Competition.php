<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    use HasUuids;

    protected $table = 'competitions';

    // ── Tipo de competição ───────────────────────────────────────────────
    const COMPETITION_TYPE_STATE    = 'state';
    const COMPETITION_TYPE_NATIONAL = 'national';
    const COMPETITION_TYPE_COPA     = 'copa';

    // ── Formato ──────────────────────────────────────────────────────────
    const FORMAT_LEAGUE   = 'league';
    const FORMAT_KNOCKOUT = 'knockout';

    // ── Divisão ──────────────────────────────────────────────────────────
    const DIVISION_FIRST  = 'first';
    const DIVISION_SECOND = 'second';

    // ── Status ───────────────────────────────────────────────────────────
    const STATUS_WAITING     = 'waiting';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_FINISHED    = 'finished';
    const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'league_id',
        'championship_id',
        'name',
        'slug',
        'competition_type',
        'division',
        'state_id',
        'format',
        'legs',
        'teams_count',
        'promotion_spots',
        'relegation_spots',
        'status',
        'current_round',
        'total_rounds',
        'season',
        'started_at',
        'finished_at',
        'bracket_data',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
        'bracket_data' => 'array',
    ];

    public function isKnockout(): bool { return $this->format === self::FORMAT_KNOCKOUT; }

    // ── Relacionamentos ───────────────────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function championship(): BelongsTo
    {
        return $this->belongsTo(Championship::class, 'championship_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(CompetitionTeam::class, 'competition_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class, 'competition_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(CompetitionPlayer::class, 'competition_id');
    }

    // ── Helpers de status ────────────────────────────────────────────────

    public function isWaiting(): bool    { return $this->status === self::STATUS_WAITING; }
    public function isInProgress(): bool { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isFinished(): bool   { return $this->status === self::STATUS_FINISHED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }

    // ── Helpers de tipo de competição ────────────────────────────────────

    public function isStateChampionship(): bool    { return $this->competition_type === self::COMPETITION_TYPE_STATE; }
    public function isNationalChampionship(): bool { return $this->competition_type === self::COMPETITION_TYPE_NATIONAL; }

    // ── Helpers de divisão ───────────────────────────────────────────────

    public function isFirstDivision(): bool  { return $this->division === self::DIVISION_FIRST; }
    public function isSecondDivision(): bool { return $this->division === self::DIVISION_SECOND; }

    /**
     * Rótulo da divisão para exibição.
     * Estadual: "A1" / "A2"  |  Nacional: "Série A" / "Série B"
     */
    public function divisionLabel(): string
    {
        if ($this->isNationalChampionship()) {
            return $this->isFirstDivision() ? 'Série A' : 'Série B';
        }
        return $this->isFirstDivision() ? 'A1' : 'A2';
    }

    /**
     * Rótulo compacto para listagens: "Paulistão A1", "Brasileiro Série A", etc.
     */
    public function shortLabel(): string
    {
        return $this->name . ($this->division ? ' · ' . $this->divisionLabel() : '');
    }
}
