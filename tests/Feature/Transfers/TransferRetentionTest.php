<?php

use App\Models\CompetitionTransferOffer;
use App\Services\TransferService;

// C3 — contra-proposta de retenção precisa poder terminar dos dois jeitos

function retentionScenario(): array
{
    $league = makeLeague();

    $seller = makeLeagueTeam($league);
    $buyer  = makeLeagueTeam($league);

    makeSquad($seller, 17);
    makeSquad($buyer, 16);

    $comp = makeCompetition($league);
    makeCompetitionTeam($comp, $buyer,  ['points' => 10, 'wins' => 3]);
    makeCompetitionTeam($comp, $seller, ['points' => 0,  'wins' => 0]);

    // Alvo: reserva de 24 anos, salário 10k — pediu 13k na contra-proposta
    $player = makePlayer($seller, ['wage' => 10_000]);

    $buyerCompTeam = $buyer->fresh()->competitionTeams()->first();

    $offer = CompetitionTransferOffer::create([
        'listing_id'            => null,
        'buyer_team_id'         => $buyerCompTeam->id,
        'competition_player_id' => $player->id,
        'offered_fee'           => 1_000_000,
        'offered_wage'          => 20_000,
        'contract_rounds'       => 2,
        'status'                => 'countered',
        'counter_price'         => 13_000,
    ]);

    return [$offer, $player, $seller, $buyer];
}

it('retém o jogador quando a retenção cobre o valor pedido na contra-proposta', function () {
    [$offer, $player, $seller] = retentionScenario();

    app(TransferService::class)->retentionOffer($offer, 13_000);

    $player->refresh();
    expect($offer->fresh()->status)->toBe('rejected_player')
        ->and($player->league_team_id)->toBe($seller->id)
        ->and((int) $player->wage)->toBe(13_000);
});

it('perde o jogador quando a retenção é insuficiente diante de proposta externa atraente', function () {
    [$offer, $player, $seller, $buyer] = retentionScenario();

    // Retenção abaixo do pedido (13k) e proposta externa paga quase o dobro
    app(TransferService::class)->retentionOffer($offer, 10_500);

    $player->refresh();
    expect($offer->fresh()->status)->toBe('accepted')
        ->and($player->league_team_id)->toBe($buyer->id)
        ->and((int) $player->wage)->toBe(20_000);
});
