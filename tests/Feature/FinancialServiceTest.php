<?php

use App\Models\CompetitionTransaction;
use App\Services\FinancialService;

// A4 — salários debitados COM registro no extrato

it('debita os salários semanais e registra a transação no extrato', function () {
    $league = makeLeague();
    $team   = makeLeagueTeam($league, ['budget' => 100_000]);

    $comp     = makeCompetition($league, ['current_round' => 3]);
    $compTeam = makeCompetitionTeam($comp, $team);

    makePlayer($team, ['wage' => 6_000]);
    makePlayer($team, ['wage' => 4_000]);
    makePlayer($team, ['wage' => 9_999, 'status' => 'free_agent']); // fora da folha

    app(FinancialService::class)->deductWeeklySalaries($league);

    expect((int) $team->fresh()->budget)->toBe(90_000);

    $tx = CompetitionTransaction::where('competition_team_id', $compTeam->id)
        ->where('type', 'wage_payment')
        ->first();

    expect($tx)->not->toBeNull()
        ->and((int) $tx->amount)->toBe(-10_000)
        ->and((int) $tx->round)->toBe(3);
});

it('não cria transação nem débito para time sem folha salarial', function () {
    $league = makeLeague();
    $team   = makeLeagueTeam($league, ['budget' => 100_000]);

    $comp     = makeCompetition($league);
    $compTeam = makeCompetitionTeam($comp, $team);

    app(FinancialService::class)->deductWeeklySalaries($league);

    expect((int) $team->fresh()->budget)->toBe(100_000)
        ->and(CompetitionTransaction::where('competition_team_id', $compTeam->id)->count())->toBe(0);
});
