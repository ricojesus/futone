<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueTeam extends Model
{
    use HasUuids;

    protected $table = 'league_teams';

    protected $fillable = [
        'league_id',
        'team_id',
        'national_division',
        'user_id',
        'coach_id',
        'name',
        'budget',
        'fans',
        'stadium_capacity',
        'ticket_price',
        'tolerance',
        'satisfaction',
        'coach_satisfaction',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────

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

    /**
     * Stats pivot records — one per competition this team participates in.
     */
    public function competitionTeams(): HasMany
    {
        return $this->hasMany(CompetitionTeam::class, 'league_team_id');
    }

    /**
     * All players registered to this team across the league.
     */
    public function players(): HasMany
    {
        return $this->hasMany(CompetitionPlayer::class, 'league_team_id');
    }

    /**
     * All lineups for this team within the league.
     */
    public function lineups(): HasMany
    {
        return $this->hasMany(CompetitionLineup::class, 'league_team_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Returns true when the team is CPU-controlled (no human manager).
     */
    public function isCpu(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Active lineup for a given round.
     * Returns the round-specific override if it exists, otherwise the default (round = 0).
     */
    public function activeLineup(int $round = 0): ?CompetitionLineup
    {
        return $this->lineups()
            ->where('status', 'active')
            ->whereIn('round', [$round, 0])
            ->orderByDesc('round')
            ->first();
    }

    // ── Satisfação e demissão ─────────────────────────────────────────

    /**
     * Limiar de demissão calculado a partir da tolerância do clube.
     *
     * tolerance=10 (exigente) → threshold≈33  demite facilmente
     * tolerance=50 (médio)    → threshold≈20
     * tolerance=100 (paciente)→ threshold≈5   quase nunca demite
     */
    public function firingThreshold(): int
    {
        return (int) max(5, round((110 - $this->tolerance) / 3));
    }

    /**
     * Retorna true quando a satisfação do clube COM O TÉCNICO caiu abaixo do limiar de demissão.
     * Maior tolerância = clube mais paciente = limiar mais baixo = mais difícil demitir.
     *
     * Não confundir com `satisfaction` (torcida com o clube, usada na bilheteria) —
     * `coach_satisfaction` é resetada a cada troca de técnico (ver SatisfactionService).
     */
    public function shouldFireCoach(): bool
    {
        return $this->coach_satisfaction < $this->firingThreshold();
    }

    /**
     * Pool de técnico desta liga: o registro LeagueCoach vinculado a este time.
     */
    public function leagueCoach(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LeagueCoach::class, 'league_team_id');
    }
}
