<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'position',
        'nationality',
        'age',
        'strength',
        'stamina',
        'photo',
    ];

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
