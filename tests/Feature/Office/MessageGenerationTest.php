<?php

use App\Models\LeagueMessage;
use App\Models\User;
use App\Services\FinancialService;
use App\Services\SatisfactionService;
use App\Services\TransferService;

// Spec 005 / US-2 — mensagens nascem dos eventos do jogo

it('gera mensagem de salários semanais com alerta de saldo baixo', function () {
    $user   = User::factory()->create();
    $league = makeLeague();
    // Folha de 17 × 10.000 = 170.000 > saldo restante após débito (200.000 − 170.000 = 30.000)
    $team   = makeLeagueTeam($league, ['user_id' => $user->id, 'budget' => 200_000]);
    makeSquad($team, 17);

    $cpu = makeLeagueTeam($league, ['budget' => 200_000]);
    makeSquad($cpu, 17);

    app(FinancialService::class)->deductWeeklySalaries($league);

    $message = LeagueMessage::where('user_id', $user->id)
        ->where('type', LeagueMessage::TYPE_FINANCIAL)
        ->first();

    expect($message)->not->toBeNull();
    expect($message->title)->toBe('Salários semanais pagos');
    expect($message->body)->toContain('Atenção');

    // CPU não recebe mensagem
    expect(LeagueMessage::count())->toBe(1);
});

it('gera mensagem de cota de TV ao pagar a cota de uma competição', function () {
    $user   = User::factory()->create();
    $league = makeLeague();
    $team   = makeLeagueTeam($league, ['user_id' => $user->id]);
    $comp   = makeCompetition($league, ['name' => 'Estadual Teste']);
    makeCompetitionTeam($comp, $team);

    app(FinancialService::class)->payTvQuotaFor($comp);

    $message = LeagueMessage::where('user_id', $user->id)->first();

    expect($message)->not->toBeNull();
    expect($message->title)->toContain('Cota de TV');
    expect($team->fresh()->budget)->toBe(12_000_000); // 10M + 2M (estadual 1ª divisão)
});

it('avisa o vendedor humano quando recebe proposta por um jogador', function () {
    $buyerUser  = User::factory()->create();
    $sellerUser = User::factory()->create();

    $league = makeLeague();
    $buyer  = makeLeagueTeam($league, ['user_id' => $buyerUser->id, 'budget' => 20_000_000]);
    $seller = makeLeagueTeam($league, ['user_id' => $sellerUser->id]);

    $comp = makeCompetition($league);
    $buyerCompTeam = makeCompetitionTeam($comp, $buyer);
    makeCompetitionTeam($comp, $seller);

    makeSquad($buyer, 16);
    makeSquad($seller, 17);
    $target = makePlayer($seller, ['name' => 'Craque Alvo']);

    app(TransferService::class)->makeDirectOffer($buyerCompTeam, $target, 1_000_000, 15_000, 2);

    $message = LeagueMessage::where('user_id', $sellerUser->id)
        ->where('type', LeagueMessage::TYPE_TRANSFER)
        ->first();

    expect($message)->not->toBeNull();
    expect($message->title)->toContain('Craque Alvo');
});

it('avisa o técnico humano quando a satisfação entra na zona crítica', function () {
    $user   = User::factory()->create();
    $league = makeLeague();
    // tolerance 50 → threshold 20; satisfação 22 está na zona crítica (< 25), mas acima do limiar
    $team = makeLeagueTeam($league, ['user_id' => $user->id, 'satisfaction' => 22, 'tolerance' => 50]);
    makeSquad($team, 15);

    app(SatisfactionService::class)->checkFirings($league);

    // Não foi demitido…
    expect($team->fresh()->user_id)->toBe($user->id);

    // …mas recebeu o aviso
    $message = LeagueMessage::where('user_id', $user->id)
        ->where('type', LeagueMessage::TYPE_CLUB)
        ->first();

    expect($message)->not->toBeNull();
    expect($message->title)->toBe('A diretoria está impaciente');
});
