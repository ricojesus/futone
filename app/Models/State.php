<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'country_id',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'state_id');
    }

    public function fullName(): string
    {
        return "{$this->name} ({$this->code})";
    }
}
