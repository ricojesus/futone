<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionLineup extends Model
{
    use HasUuids;

    protected $table = 'competition_lineups';

    protected $fillable = [
        'competition_id',
        'league_team_id',
        'formation',
        'round',
        'status',
    ];

    // ── Constantes ───────────────────────────────────────────────────────

    /**
     * Formações suportadas: nome → {defender, midfielder, forward}.
     * O número de goleiros é sempre 1.
     */
    public const FORMATIONS = [
        '4-4-2'   => ['defender' => 4, 'midfielder' => 4, 'forward' => 2],
        '4-3-3'   => ['defender' => 4, 'midfielder' => 3, 'forward' => 3],
        '3-5-2'   => ['defender' => 3, 'midfielder' => 5, 'forward' => 2],
        '5-3-2'   => ['defender' => 5, 'midfielder' => 3, 'forward' => 2],
        '4-5-1'   => ['defender' => 4, 'midfielder' => 5, 'forward' => 1],
        '3-4-3'   => ['defender' => 3, 'midfielder' => 4, 'forward' => 3],
        '5-4-1'   => ['defender' => 5, 'midfielder' => 4, 'forward' => 1],
        '4-2-3-1' => ['defender' => 4, 'midfielder' => 5, 'forward' => 1],
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function leagueTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'league_team_id');
    }

    public function lineupPlayers(): HasMany
    {
        return $this->hasMany(CompetitionLineupPlayer::class, 'lineup_id');
    }

    public function starters(): HasMany
    {
        return $this->hasMany(CompetitionLineupPlayer::class, 'lineup_id')
            ->where('is_starter', true)
            ->orderBy('slot');
    }

    /**
     * Jogadores titulares como CompetitionPlayers (com pivot).
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(
            CompetitionPlayer::class,
            'competition_lineup_players',
            'lineup_id',
            'competition_player_id'
        )
        ->withPivot(['role', 'is_starter', 'slot'])
        ->withTimestamps();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Retorna a configuração posicional da formação atual.
     */
    public function slots(): array
    {
        return self::FORMATIONS[$this->formation]
            ?? self::FORMATIONS['4-4-2'];
    }

    public function expectedStarterCount(): int
    {
        return 11;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDefault(): bool
    {
        return $this->round === 0;
    }
}
