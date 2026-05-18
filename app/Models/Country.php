<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'flag',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'country_id');
    }
}
