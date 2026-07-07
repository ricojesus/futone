<?php

use App\Models\CompetitionLineupPlayer;
use App\Services\TransferService;

// C4 + M7 — execução da transferência e recusa educada do vendedor CPU

it('remove o jogador vendido da escalação do vendedor e movimenta os saldos', function () {
    $league = makeLeague();

    $seller = makeLeagueTeam($league);                       // CPU (sem user_id)
    $buyer  = makeLeagueTeam($league, ['budget' => 5_000_000]);

    $sellerSquad = makeSquad($seller, 17);
    makeSquad($buyer, 16);

    // Comprador em divisão superior (nacional A) ao vendedor (estadual A2)
    $compBuyer  = makeCompetition($league, [
        'competition_type' => \App\Models\Competition::COMPETITION_TYPE_NATIONAL,
        'division'         => \App\Models\Competition::DIVISION_FIRST,
    ]);
    $compSeller = makeCompetition($league, [
        'division' => \App\Models\Competition::DIVISION_SECOND,
    ]);
    $buyerCompTeam = makeCompetitionTeam($compBuyer, $buyer, ['points' => 10, 'wins' => 3]);
    makeCompetitionTeam($compSeller, $seller);

    // Alvo é titular na escalação persistida do vendedor
    makeLineup($seller, $sellerSquad->take(11));
    $alvo = $sellerSquad->get(5);

    expect(CompetitionLineupPlayer::where('competition_player_id', $alvo->id)->exists())->toBeTrue();

    $offer = app(TransferService::class)->makeDirectOffer(
        buyerCompTeam:  $buyerCompTeam,
        player:         $alvo,
        offeredFee:     1_000_000,
        offeredWage:    20_000,
        contractYears:  2,
    );

    expect($offer->status)->toBe('accepted')
        ->and($alvo->fresh()->league_team_id)->toBe($buyer->id)
        // C4: nenhum resquício do jogador em escalações antigas
        ->and(CompetitionLineupPlayer::where('competition_player_id', $alvo->id)->exists())->toBeFalse()
        ->and((int) $buyer->fresh()->budget)->toBe(4_000_000)
        ->and((int) $seller->fresh()->budget)->toBe(11_000_000);
});

it('rejeita a oferta sem estourar erro quando o vendedor CPU não pode vender', function () {
    $league = makeLeague();

    $seller = makeLeagueTeam($league);
    $buyer  = makeLeagueTeam($league);

    makeSquad($seller, 15); // elenco no mínimo — não pode vender
    makeSquad($buyer, 16);

    $comp = makeCompetition($league);
    $buyerCompTeam = makeCompetitionTeam($comp, $buyer);
    makeCompetitionTeam($comp, $seller);

    $alvo = $seller->fresh()->players()->where('position', 'midfielder')->first();

    $offer = app(TransferService::class)->makeDirectOffer(
        buyerCompTeam:  $buyerCompTeam,
        player:         $alvo,
        offeredFee:     1_000_000,
        offeredWage:    20_000,
        contractYears:  2,
    );

    // M7: rejeição educada, não HTTP 422
    expect($offer->status)->toBe('rejected_team')
        ->and($alvo->fresh()->league_team_id)->toBe($seller->id);
});
