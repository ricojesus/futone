<?php

use App\Models\CompetitionTeam;

// A2 — critério oficial: pontos → vitórias → saldo → gols pró

it('ordena a classificação por pontos antes de vitórias e saldo', function () {
    $league = makeLeague();
    $comp   = makeCompetition($league, ['teams_count' => 3]);

    $lider    = makeCompetitionTeam($comp, makeLeagueTeam($league), [
        'points' => 10, 'wins' => 2, 'goals_for' => 5, 'goals_against' => 4,
    ]);
    $vice     = makeCompetitionTeam($comp, makeLeagueTeam($league), [
        'points' => 9, 'wins' => 3, 'goals_for' => 12, 'goals_against' => 2,
    ]);
    $lanterna = makeCompetitionTeam($comp, makeLeagueTeam($league), [
        'points' => 3, 'wins' => 1, 'goals_for' => 2, 'goals_against' => 9,
    ]);

    // Collection (usado nas views e na seleção da Copa)
    $sorted = CompetitionTeam::sortStandings($comp->fresh()->teams);
    expect($sorted->pluck('id')->all())->toBe([$lider->id, $vice->id, $lanterna->id]);

    // Query scope (usado no tableRank do mercado)
    $viaScope = CompetitionTeam::where('competition_id', $comp->id)
        ->standingsOrder()
        ->pluck('id')
        ->all();
    expect($viaScope)->toBe([$lider->id, $vice->id, $lanterna->id]);
});

it('desempata por vitórias e depois por saldo de gols', function () {
    $league = makeLeague();
    $comp   = makeCompetition($league, ['teams_count' => 3]);

    $porVitorias = makeCompetitionTeam($comp, makeLeagueTeam($league), [
        'points' => 9, 'wins' => 3, 'goals_for' => 4, 'goals_against' => 4,
    ]);
    $porSaldo    = makeCompetitionTeam($comp, makeLeagueTeam($league), [
        'points' => 9, 'wins' => 2, 'goals_for' => 9, 'goals_against' => 1,
    ]);
    $terceiro    = makeCompetitionTeam($comp, makeLeagueTeam($league), [
        'points' => 9, 'wins' => 2, 'goals_for' => 5, 'goals_against' => 3,
    ]);

    $sorted = CompetitionTeam::sortStandings($comp->fresh()->teams);
    expect($sorted->pluck('id')->all())->toBe([$porVitorias->id, $porSaldo->id, $terceiro->id]);
});
