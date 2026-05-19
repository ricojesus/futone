<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueTransferOffer extends Model
{
    use HasUuids;

    protected $fillable = [
        'listing_id',
        'buyer_team_id',
        'league_player_id',
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
        return $this->belongsTo(LeagueTransferListing::class, 'listing_id');
    }

    public function buyerTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'buyer_team_id');
    }

    /** Jogador alvo — via listing ou diretamente (free agent) */
    public function player(): BelongsTo
    {
        return $this->belongsTo(LeaguePlayer::class, 'league_player_id');
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
