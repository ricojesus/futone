<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionTransferOffer extends Model
{
    use HasUuids;

    protected $table = 'competition_transfer_offers';

    protected $fillable = [
        'listing_id',
        'buyer_team_id',
        'competition_player_id',
        'offered_fee',
        'offered_wage',
        'contract_rounds',
        'status',
        'counter_price',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(CompetitionTransferListing::class, 'listing_id');
    }

    public function buyerTeam(): BelongsTo
    {
        return $this->belongsTo(CompetitionTeam::class, 'buyer_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(CompetitionPlayer::class, 'competition_player_id');
    }

    public function isPending(): bool     { return $this->status === 'pending'; }
    public function isAccepted(): bool    { return $this->status === 'accepted'; }
    public function isRejected(): bool    { return in_array($this->status, ['rejected_team', 'rejected_player', 'withdrawn']); }
    public function isCountered(): bool   { return $this->status === 'countered'; }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'         => 'Aguardando clube',
            'pending_player'  => 'Aguardando jogador',
            'accepted'        => 'Aceita',
            'rejected_team'   => 'Recusada pelo clube',
            'rejected_player' => 'Jogador recusou os termos',
            'countered'       => 'Contraproposta recebida',
            'withdrawn'       => 'Cancelada',
            default           => $this->status,
        };
    }
}
