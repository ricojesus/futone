<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Team extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'coach_id',
        'country_id',
        'state_id',
        'badge',
        'overall',
        'state_division',
        'national_division',
        'tolerance',
        'fans_base',
        'stadium_capacity',
    ];

    // Divisões possíveis
    const DIVISION_FIRST  = 'first';
    const DIVISION_SECOND = 'second';

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }
}
