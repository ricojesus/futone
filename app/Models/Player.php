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
        'team_id',
        'country_id',
        'age',
        'strength',
        'stamina',
        'potential',
        'photo',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

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
