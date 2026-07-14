<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LeagueMessage extends Model
{
    use HasUuids;

    const TYPE_FINANCIAL  = 'financial';
    const TYPE_TRANSFER   = 'transfer';
    const TYPE_MATCH      = 'match';
    const TYPE_LINEUP     = 'lineup';
    const TYPE_CLUB       = 'club';
    const TYPE_INVITATION = 'invitation';

    protected $fillable = [
        'league_id',
        'user_id',
        'league_team_id',
        'type',
        'title',
        'body',
        'subject_type',
        'subject_id',
        'global_round',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
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

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * URL da tela do objeto de origem, quando navegável.
     */
    public function subjectUrl(): ?string
    {
        return match ($this->subject_type) {
            CompetitionTransferOffer::class => route('leagues.transfers.offers', $this->league_id),
            CompetitionMatch::class => $this->matchUrl(),
            LeagueTeam::class => route('leagues.teams.show', [$this->league_id, $this->subject_id]),
            default => null,
        };
    }

    private function matchUrl(): ?string
    {
        $match = $this->subject;

        if (! $match instanceof CompetitionMatch) {
            return null;
        }

        return route('matches.show', [$this->league_id, $match->competition_id, $match->id]);
    }
}
