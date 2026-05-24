<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasUuids;

    // ── Tipo de acesso (original) ────────────────────────────────────────
    const ACCESS_PUBLIC  = 'public';
    const ACCESS_PRIVATE = 'private';

    // ── Tipo de competição ───────────────────────────────────────────────
    const COMPETITION_TYPE_STATE    = 'state';
    const COMPETITION_TYPE_NATIONAL = 'national';

    // ── Divisão ──────────────────────────────────────────────────────────
    const DIVISION_FIRST  = 'first';   // A1 / Série A
    const DIVISION_SECOND = 'second';  // A2 / Série B

    // ── Status ───────────────────────────────────────────────────────────
    const STATUS_WAITING     = 'waiting';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_FINISHED    = 'finished';
    const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'state_id',
        'competition_type',
        'division',
        'type',
        'invite_code',
        'max_teams',
        'team_assignment',
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

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(LeagueTeam::class, 'league_id');
    }

    public function championships(): HasMany
    {
        return $this->hasMany(LeagueChampionship::class, 'league_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(LeagueMatch::class, 'league_id');
    }

    // ── Helpers de status ────────────────────────────────────────────────

    public function isWaiting(): bool    { return $this->status === self::STATUS_WAITING; }
    public function isInProgress(): bool { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isFinished(): bool   { return $this->status === self::STATUS_FINISHED; }

    // ── Helpers de tipo de competição ────────────────────────────────────

    public function isStateChampionship(): bool    { return $this->competition_type === self::COMPETITION_TYPE_STATE; }
    public function isNationalChampionship(): bool { return $this->competition_type === self::COMPETITION_TYPE_NATIONAL; }
    public function isSystemLeague(): bool         { return $this->competition_type !== null; }

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

    // ── Helpers de atribuição de times ───────────────────────────────────

    public function usesRandomAssignment(): bool  { return $this->team_assignment === 'random'; }
    public function usesChoiceAssignment(): bool  { return $this->team_assignment === 'choice'; }

    public function teamAssignmentLabel(): string
    {
        return match ($this->team_assignment) {
            'random' => 'Sorteio',
            default  => 'Escolha livre',
        };
    }
}
