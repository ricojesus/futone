<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Técnico dentro do contexto de uma liga.
 *
 * league_team_id = null → técnico livre (disponível no mercado da liga)
 * league_team_id = X    → técnico ativo gerenciando o time X
 */
class LeagueCoach extends Model
{
    use HasUuids;

    protected $table = 'league_coaches';

    protected $fillable = [
        'league_id',
        'coach_id',
        'league_team_id',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    public function leagueTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'league_team_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function isFreeAgent(): bool
    {
        return $this->league_team_id === null;
    }
}
