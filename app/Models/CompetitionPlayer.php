<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompetitionPlayer extends Model
{
    use HasUuids;

    protected $table = 'competition_players';

    protected $fillable = [
        'competition_id',
        'competition_team_id',
        'player_id',
        'country_id',
        'name',
        'position',
        'age',
        'strength',
        'stamina',
        'potential',
        'status',
        'wage',
        'market_value',
        'contract_until',
        'wage_expectation_factor',
        'form_factor',
        'fitness',
        'injured_until',
        'joined_at',
        'released_at',
    ];

    protected $casts = [
        'joined_at'   => 'datetime',
        'released_at' => 'datetime',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function competitionTeam(): BelongsTo
    {
        return $this->belongsTo(CompetitionTeam::class, 'competition_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function activeTransferListing(): HasOne
    {
        return $this->hasOne(CompetitionTransferListing::class, 'competition_player_id')
            ->where('status', 'open');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(CompetitionTransfer::class, 'competition_player_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === 'active';
    }

    public function isFreeAgent(): bool
    {
        return $this->status === 'free_agent';
    }

    public function isInjured(): bool
    {
        return $this->status === 'injured';
    }

    public function canPlay(): bool
    {
        return $this->status === 'active';
    }

    public function isListedForSale(): bool
    {
        return $this->activeTransferListing()->exists();
    }

    public function positionLabel(): string
    {
        return Player::$positions[$this->position] ?? ucfirst($this->position);
    }

    public function formattedWage(): string
    {
        return 'R$ ' . number_format($this->wage, 0, ',', '.');
    }

    public function formattedMarketValue(): string
    {
        return 'R$ ' . number_format($this->market_value, 0, ',', '.');
    }

    /**
     * Retorna o delta percentual de mercado causado pela forma.
     */
    public function formImpact(): string
    {
        $delta = (float) $this->form_factor - 1.00;
        $pct   = round($delta * 100);
        return ($pct >= 0 ? '+' : '') . $pct . '%';
    }
}
