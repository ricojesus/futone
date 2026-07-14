<?php

use App\Models\CompetitionMatch;
use App\Models\League;
use App\Models\MatchState;
use App\Models\User;

// Fixes 2026-07-14 — dono precisa escalar antes de avançar; intervalo sem
// escalação salva sempre exibe o botão de 2º tempo (senão a liga trava)

it('bloqueia o avanço e redireciona o dono para a escalação quando o time dele não está escalado', function () {
    $league = makeLeague();
    $owner  = $league->owner;
    $team   = makeLeagueTeam($league, ['user_id' => $owner->id]);
    makeSquad($team, 15);

    $this->actingAs($owner)
        ->post(route('leagues.advance-week', $league))
        ->assertRedirect(route('leagues.lineup.edit', [$league, $team]));

    // A rodada global não andou
    expect($league->fresh()->global_round)->toBe(0);
});

it('permite o avanço quando o dono tem 11 titulares escalados', function () {
    $league = makeLeague();
    $owner  = $league->owner;
    $team   = makeLeagueTeam($league, ['user_id' => $owner->id]);
    $squad  = makeSquad($team, 15);
    makeLineup($team, $squad->take(11));

    $this->actingAs($owner)
        ->post(route('leagues.advance-week', $league))
        ->assertRedirect(route('leagues.show', $league));

    expect($league->fresh()->global_round)->toBe(1);
});

it('exibe o botão de 2º tempo no intervalo mesmo sem escalação salva', function () {
    $user   = User::factory()->create();
    $league = makeLeague();
    $mine   = makeLeagueTeam($league, ['user_id' => $user->id]);
    $rival  = makeLeagueTeam($league);
    makeSquad($mine, 15);

    $comp     = makeCompetition($league);
    $myComp   = makeCompetitionTeam($comp, $mine);
    $cpuComp  = makeCompetitionTeam($comp, $rival);

    $match = CompetitionMatch::create([
        'competition_id' => $comp->id,
        'home_team_id'   => $myComp->id,
        'away_team_id'   => $cpuComp->id,
        'round'          => 1,
        'leg'            => 1,
        'status'         => 'halftime',
    ]);

    MatchState::create([
        'competition_match_id' => $match->id,
        'state'                => ['events' => [], 'homeScore' => 0, 'awayScore' => 0],
    ]);

    $this->actingAs($user)
        ->get(route('matches.halftime', [$league, $comp, $match]))
        ->assertOk()
        ->assertSee('escalação automática')
        ->assertSee('Iniciar 2º Tempo');
});
