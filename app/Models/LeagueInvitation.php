<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueInvitation extends Model
{
    use HasUuids;

    const STATUS_PENDING  = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_DECLINED = 'declined';
    const STATUS_EXPIRED  = 'expired';

    protected $fillable = [
        'league_id',
        'user_id',
        'league_team_id',
        'status',
        'global_round',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leagueTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Convite válido: pendente e da rodada global vigente da liga.
     */
    public function isOpen(League $league): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->global_round >= $league->global_round;
    }
}
