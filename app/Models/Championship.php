<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Championship extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'country_id',
        'state_id',
        'type',
        'legs',
        'teams_count',
        'promotion_spots',
        'relegation_spots',
    ];

    public static array $types = [
        'league' => 'Pontos corridos',
        'cup'    => 'Mata-mata',
        'mixed'  => 'Grupos + Mata-mata',
    ];

    public static array $legs = [
        'single' => 'Jogo único',
        'double' => 'Ida e volta',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function leagueChampionships(): HasMany
    {
        return $this->hasMany(LeagueChampionship::class, 'championship_id');
    }

    public function scopeLabel(): string
    {
        $scope = $this->state?->code ?? $this->country?->code ?? 'Internacional';
        return "{$this->name} ({$scope})";
    }
}
