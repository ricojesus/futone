<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_championship_id',
        'league_id',
        'home_team_id',
        'away_team_id',
        'round',
        'leg',
        'status',
        'home_score',
        'away_score',
        'winner_team_id',
        'scheduled_at',
        'played_at',
        'data',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'played_at'    => 'datetime',
        'data'         => 'array',
    ];

    // ── Relacionamentos ──────────────────────────────────────────────

    public function leagueChampionship(): BelongsTo
    {
        return $this->belongsTo(LeagueChampionship::class, 'league_championship_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'away_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'winner_team_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /** Resultado formatado — ex: "2 × 1" ou "—" se ainda não jogou */
    public function scoreLabel(): string
    {
        if (!$this->isFinished()) {
            return '—';
        }

        return "{$this->home_score} × {$this->away_score}";
    }

    /** Retorna o resultado do ponto de vista do time informado */
    public function resultFor(string $leagueTeamId): string
    {
        if (!$this->isFinished()) return 'pending';

        if ($this->home_score === $this->away_score) return 'draw';

        $homeWon = $this->home_score > $this->away_score;

        if ($leagueTeamId === $this->home_team_id) {
            return $homeWon ? 'win' : 'loss';
        }

        return $homeWon ? 'loss' : 'win';
    }
}
