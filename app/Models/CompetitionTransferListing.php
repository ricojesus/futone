<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionTransferListing extends Model
{
    use HasUuids;

    protected $table = 'competition_transfer_listings';

    protected $fillable = [
        'competition_id',
        'seller_team_id',
        'competition_player_id',
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

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function sellerTeam(): BelongsTo
    {
        return $this->belongsTo(CompetitionTeam::class, 'seller_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(CompetitionPlayer::class, 'competition_player_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(CompetitionTransferOffer::class, 'listing_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function acceptsFee(int $fee): bool
    {
        return $fee >= $this->min_acceptable;
    }
}
