<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_team_id',
        'type',
        'amount',
        'description',
        'round',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function leagueTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'league_team_id');
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    public function formattedAmount(): string
    {
        $prefix = $this->amount > 0 ? '+' : '';
        return $prefix . 'R$ ' . number_format(abs($this->amount), 0, ',', '.');
    }
}
