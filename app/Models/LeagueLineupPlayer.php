<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueLineupPlayer extends Model
{
    use HasUuids;

    protected $fillable = [
        'lineup_id',
        'league_player_id',
        'role',
        'is_starter',
        'slot',
    ];

    protected $casts = [
        'is_starter' => 'boolean',
    ];

    // ── Relacionamentos ──────────────────────────────────────────────

    public function lineup(): BelongsTo
    {
        return $this->belongsTo(LeagueLineup::class, 'lineup_id');
    }

    public function leaguePlayer(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'league_player_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function roleLabel(): string
    {
        return match ($this->role) {
            'goalkeeper' => 'GOL',
            'defender'   => 'DEF',
            'midfielder' => 'MEI',
            'forward'    => 'ATA',
            default      => strtoupper($this->role),
        };
    }
}
