<?php

use App\Models\Competition;
use App\Models\CompetitionTransaction;
use App\Models\Country;
use App\Models\League;
use App\Models\State;
use App\Services\GlobalRoundService;
use App\Services\SeasonTransitionService;

// Spec 002 — Virada de Temporada Consistente

/**
 * Mundo mínimo no fim da temporada 2026 (fase national, tudo encerrado):
 *  - Estadual SP: A1 (3 times, 1 cai) + A2 (3 times, 1 sobe)
 *  - Nacional:    Série A (3 times, 1 cai) + Série B (3 times, 1 sobe)
 * Times do nacional têm national_division preenchida no LeagueTeam.
 */
function seasonEndScenario(): array
{
    $league = makeLeague(['current_phase' => League::PHASE_NATIONAL]);

    $country = Country::create(['name' => 'Brasil', 'code' => 'BR']);
    $state   = State::create(['name' => 'São Paulo', 'code' => 'SP', 'country_id' => $country->id]);

    $teams = collect(range(1, 6))->map(fn($i) => makeLeagueTeam($league, ['name' => "Clube {$i}"]));
    $teams->each(fn($t) => makePlayer($t, ['age' => 31, 'strength' => 70, 'stamina' => 70]));

    $mk = function (array $compAttrs, array $teamsWithPoints) use ($league) {
        $comp = makeCompetition($league, array_merge([
            'status'      => Competition::STATUS_FINISHED,
            'teams_count' => count($teamsWithPoints),
        ], $compAttrs));

        foreach ($teamsWithPoints as [$leagueTeam, $points]) {
            makeCompetitionTeam($comp, $leagueTeam, ['points' => $points, 'wins' => intdiv($points, 3)]);
        }

        return $comp;
    };

    // Estadual SP — A1: Clube 1 campeão, Clube 3 rebaixado; A2: Clube 4 sobe
    $mk(
        ['division' => 'first', 'state_id' => $state->id, 'relegation_spots' => 1, 'name' => 'Paulistão A1 2026'],
        [[$teams[0], 20], [$teams[1], 15], [$teams[2], 3]],
    );
    $mk(
        ['division' => 'second', 'state_id' => $state->id, 'promotion_spots' => 1, 'name' => 'Paulistão A2 2026'],
        [[$teams[3], 18], [$teams[4], 10], [$teams[5], 5]],
    );

    // Nacional — Série A: Clubes 1-3 (Clube 3 cai); Série B: Clubes 4-6 (Clube 4 sobe)
    $teams->take(3)->each(fn($t) => $t->update(['national_division' => 'first']));
    $teams->skip(3)->each(fn($t) => $t->update(['national_division' => 'second']));

    $mk(
        ['competition_type' => 'national', 'division' => 'first', 'relegation_spots' => 1, 'name' => 'Série A 2026'],
        [[$teams[0], 30], [$teams[1], 22], [$teams[2], 6]],
    );
    $mk(
        ['competition_type' => 'national', 'division' => 'second', 'promotion_spots' => 1, 'name' => 'Série B 2026'],
        [[$teams[3], 28], [$teams[4], 14], [$teams[5], 9]],
    );

    return [$league, $teams];
}

it('reseta o ciclo de fases e cria apenas estaduais na nova temporada', function () {
    [$league] = seasonEndScenario();

    app(SeasonTransitionService::class)->advanceSeason($league);

    $league->refresh();
    expect((int) $league->season)->toBe(2027)
        ->and($league->current_phase)->toBe(League::PHASE_STATE);

    $newComps = Competition::where('league_id', $league->id)->where('season', 2027)->get();

    expect($newComps)->toHaveCount(2)
        ->and($newComps->pluck('competition_type')->unique()->all())->toBe(['state'])
        ->and($newComps->every(fn($c) => $c->status === Competition::STATUS_IN_PROGRESS))->toBeTrue();
});

it('persiste promoção e rebaixamento nacionais no LeagueTeam', function () {
    [$league, $teams] = seasonEndScenario();

    app(SeasonTransitionService::class)->advanceSeason($league);

    expect($teams[3]->fresh()->national_division)->toBe('first')   // campeão da Série B subiu
        ->and($teams[2]->fresh()->national_division)->toBe('second') // lanterna da Série A caiu
        ->and($teams[0]->fresh()->national_division)->toBe('first');
});

it('cria as Séries A/B uma única vez, na transição pós-Copa, respeitando as divisões da liga', function () {
    [$league, $teams] = seasonEndScenario();

    app(SeasonTransitionService::class)->advanceSeason($league);

    // Simula o fim da fase copa da temporada 2027
    $league->refresh();
    $league->update(['current_phase' => League::PHASE_COPA]);
    Competition::where('league_id', $league->id)->where('season', 2027)
        ->update(['status' => Competition::STATUS_FINISHED]);
    makeCompetition($league, [
        'competition_type' => 'copa',
        'season'           => 2027,
        'status'           => Competition::STATUS_FINISHED,
        'name'             => 'Copa do Brasil 2027',
    ]);

    app(GlobalRoundService::class)->advance($league->fresh());

    $serieA = Competition::where('league_id', $league->id)->where('season', 2027)
        ->where('competition_type', 'national')->where('division', 'first')->get();

    expect($serieA)->toHaveCount(1); // sem duplicata

    $serieATeamIds = $serieA->first()->teams()->pluck('league_team_id');
    expect($serieATeamIds)->toContain($teams[3]->id)      // promovido está na Série A
        ->and($serieATeamIds)->not->toContain($teams[2]->id); // rebaixado não está
});

it('ignora competições de temporadas anteriores no cálculo de transições', function () {
    [$league, $teams] = seasonEndScenario();

    // Competição fantasma de 2025 com pontuação absurda do Clube 6
    $velha = makeCompetition($league, [
        'season'   => 2025,
        'division' => 'first',
        'status'   => Competition::STATUS_FINISHED,
        'name'     => 'Série A 2025',
        'competition_type' => 'national',
    ]);
    makeCompetitionTeam($velha, $teams[5], ['points' => 99, 'wins' => 33]);

    $transitions = app(SeasonTransitionService::class)->calculateTransitions($league);

    expect($transitions['national']['champion']->league_team_id)->toBe($teams[0]->id);
});

it('recalcula valor de mercado após envelhecer e preserva a satisfação', function () {
    [$league, $teams] = seasonEndScenario();

    $time    = $teams[0];
    $time->update(['satisfaction' => 83]);
    $jogador = $time->players()->first(); // 31 anos, valor pré-calculado do helper

    $valorAntes = (int) $jogador->market_value;

    app(SeasonTransitionService::class)->advanceSeason($league);

    $jogador->refresh();
    expect((int) $jogador->age)->toBe(32)
        ->and((int) $jogador->market_value)->toBeLessThan($valorAntes) // curva de idade: 31→32 derruba o valor
        ->and((int) $time->fresh()->satisfaction)->toBe(83);           // satisfação carrega para a nova temporada
});

it('paga a cota de TV na criação das competições da nova temporada', function () {
    [$league, $teams] = seasonEndScenario();

    $txAntes = CompetitionTransaction::where('type', 'prize_money')->count();

    app(SeasonTransitionService::class)->advanceSeason($league);

    // A1 (3 times × 2M) + A2 (3 times × 1M) = 6 transações novas
    $novas = CompetitionTransaction::where('type', 'prize_money')->count() - $txAntes;
    expect($novas)->toBe(6);

    // Campeão da A1 segue nela em 2027 → recebeu 2M
    $tx = CompetitionTransaction::where('type', 'prize_money')
        ->whereHas('competitionTeam', fn($q) => $q->where('league_team_id', $teams[0]->id))
        ->orderByDesc('created_at')
        ->first();

    expect((int) $tx->amount)->toBe(2_000_000);
});
