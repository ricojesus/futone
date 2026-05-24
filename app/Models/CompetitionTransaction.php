<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionTransaction extends Model
{
    use HasUuids;

    protected $table = 'competition_transactions';

    protected $fillable = [
        'competition_team_id',
        'type',
        'amount',
        'description',
        'round',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function competitionTeam(): BelongsTo
    {
        return $this->belongsTo(CompetitionTeam::class, 'competition_team_id');
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
