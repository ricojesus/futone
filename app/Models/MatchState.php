<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchState extends Model
{
    use HasUuids;

    protected $fillable = [
        'competition_match_id',
        'state',
    ];

    protected $casts = [
        'state' => 'array',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(CompetitionMatch::class, 'competition_match_id');
    }
}
