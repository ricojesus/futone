<?php

use App\Models\User;

// C2 — o mercado nunca pode vazar jogadores de outras ligas (salas)

it('lista apenas jogadores da própria liga no mercado', function () {
    $user    = User::factory()->create();
    $leagueA = makeLeague();
    $myTeam  = makeLeagueTeam($leagueA, ['user_id' => $user->id]);
    $rivalA  = makeLeagueTeam($leagueA);

    makePlayer($rivalA, ['name' => 'Atacante Da Liga Alfa']);

    $leagueB = makeLeague();
    $teamB   = makeLeagueTeam($leagueB);
    makePlayer($teamB, ['name' => 'Atacante Da Liga Beta']);

    $comp = makeCompetition($leagueA);
    makeCompetitionTeam($comp, $myTeam);
    makeCompetitionTeam($comp, $rivalA);

    $this->actingAs($user)
        ->get(route('leagues.transfers.index', $leagueA))
        ->assertOk()
        ->assertSee('Atacante Da Liga Alfa')
        ->assertDontSee('Atacante Da Liga Beta');
});

it('bloqueia proposta por jogador de outra liga', function () {
    $user    = User::factory()->create();
    $leagueA = makeLeague();
    $myTeam  = makeLeagueTeam($leagueA, ['user_id' => $user->id]);

    $comp = makeCompetition($leagueA);
    makeCompetitionTeam($comp, $myTeam);
    makeSquad($myTeam, 16);

    $leagueB = makeLeague();
    $teamB   = makeLeagueTeam($leagueB);
    $alvo    = makePlayer($teamB);

    $this->actingAs($user)
        ->post(route('leagues.transfers.store', $leagueA), [
            'player_id'      => $alvo->id,
            'offered_fee'    => 1_000_000,
            'offered_wage'   => 20_000,
            'contract_years' => 2,
        ])
        ->assertNotFound();

    expect($alvo->fresh()->league_team_id)->toBe($teamB->id);
});

// M8 — free agents da liga aparecem no mercado

it('exibe free agents da liga no mercado', function () {
    $user    = User::factory()->create();
    $league  = makeLeague();
    $myTeam  = makeLeagueTeam($league, ['user_id' => $user->id]);
    $rival   = makeLeagueTeam($league);

    makePlayer($rival, ['name' => 'Meia Sem Clube', 'status' => 'free_agent']);

    $comp = makeCompetition($league);
    makeCompetitionTeam($comp, $myTeam);
    makeCompetitionTeam($comp, $rival);

    $this->actingAs($user)
        ->get(route('leagues.transfers.index', $league))
        ->assertOk()
        ->assertSee('Meia Sem Clube');
});
