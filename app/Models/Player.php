<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'position',
        'country_id',
        'age',
        'strength',
        'stamina',
        'photo',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public static array $positions = [
        'goalkeeper' => 'Goleiro',
        'defender'   => 'Defesa',
        'midfielder' => 'Meio',
        'forward'    => 'Ataque',
    ];

    public function positionLabel(): string
    {
        return self::$positions[$this->position] ?? ucfirst($this->position);
    }
}
