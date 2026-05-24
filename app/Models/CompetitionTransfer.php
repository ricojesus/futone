<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionTransfer extends Model
{
    use HasUuids;

    protected $table = 'competition_transfers';

    protected $fillable = [
        'competition_id',
        'from_team_id',
        'to_team_id',
        'competition_player_id',
        'fee',
        'wage',
        'contract_until',
        'round',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(CompetitionTeam::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(CompetitionTeam::class, 'to_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(CompetitionPlayer::class, 'competition_player_id');
    }

    public function isFreeAgent(): bool
    {
        return $this->fee === 0 && is_null($this->from_team_id);
    }
}
