<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'state_id',
        'type',
        'invite_code',
        'max_teams',
        'team_assignment',
        'status',
        'season',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(LeagueTeam::class, 'league_id');
    }

    public function championships(): HasMany
    {
        return $this->hasMany(LeagueChampionship::class, 'league_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(LeagueMatch::class, 'league_id');
    }

    public function isWaiting(): bool    { return $this->status === 'waiting'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }
    public function isFinished(): bool   { return $this->status === 'finished'; }

    public function usesRandomAssignment(): bool  { return $this->team_assignment === 'random'; }
    public function usesChoiceAssignment(): bool  { return $this->team_assignment === 'choice'; }

    public function teamAssignmentLabel(): string
    {
        return match ($this->team_assignment) {
            'random' => 'Sorteio',
            default  => 'Escolha livre',
        };
    }
}
