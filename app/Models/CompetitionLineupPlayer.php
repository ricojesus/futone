<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionLineupPlayer extends Model
{
    use HasUuids;

    protected $table = 'competition_lineup_players';

    protected $fillable = [
        'lineup_id',
        'competition_player_id',
        'role',
        'is_starter',
        'slot',
    ];

    protected $casts = [
        'is_starter' => 'boolean',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────

    public function lineup(): BelongsTo
    {
        return $this->belongsTo(CompetitionLineup::class, 'lineup_id');
    }

    public function competitionPlayer(): BelongsTo
    {
        return $this->belongsTo(CompetitionPlayer::class, 'competition_player_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

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
