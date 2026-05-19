<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueTransferListing extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_id',
        'seller_team_id',
        'league_player_id',
        'asking_price',
        'min_acceptable',
        'status',
        'listed_at',
        'expires_at',
    ];

    protected $casts = [
        'listed_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function sellerTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'seller_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'league_player_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(LeagueTransferOffer::class, 'listing_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Verifica se uma proposta cobre o mínimo aceitável pelo clube */
    public function acceptsFee(int $fee): bool
    {
        return $fee >= $this->min_acceptable;
    }
}
