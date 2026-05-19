<?php

namespace App\Services;

use App\Models\LeaguePlayer;
use App\Models\LeagueTeam;
use App\Models\LeagueTransfer;
use App\Models\LeagueTransferListing;
use App\Models\LeagueTransferOffer;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function __construct(
        private readonly MarketValueService $marketValue,
    ) {}

    // ── Listagem ─────────────────────────────────────────────────────

    /**
     * Coloca um jogador à venda no mercado da liga.
     *
     * @param  int  $askingPrice  Valor pedido publicamente
     * @param  int  $minAcceptable  Valor mínimo real (privado)
     */
    public function listForSale(
        LeagueTeam $seller,
        LeaguePlayer $player,
        int $askingPrice,
        int $minAcceptable,
    ): LeagueTransferListing {
        // Garante que não há listagem aberta para o mesmo jogador
        LeagueTransferListing::where('league_player_id', $player->id)
            ->where('status', 'open')
            ->update(['status' => 'withdrawn']);

        return LeagueTransferListing::create([
            'league_id'        => $seller->league_id,
            'seller_team_id'   => $seller->id,
            'league_player_id' => $player->id,
            'asking_price'     => $askingPrice,
            'min_acceptable'   => $minAcceptable,
            'status'           => 'open',
        ]);
    }

    // ── Oferta para jogador com clube ────────────────────────────────

    /**
     * Faz uma oferta por um jogador listado no mercado.
     *
     * Fluxo:
     *   1. Verifica budget do comprador
     *   2. Cria a oferta como 'pending'
     *   3. Se clube vendedor é CPU → resolve Fase 1 imediatamente
     *   4. Se clube vendedor é humano → fica 'pending' até o manager decidir
     */
    public function makeOffer(
        LeagueTeam $buyer,
        LeagueTransferListing $listing,
        int $offeredFee,
        int $offeredWage,
        int $contractRounds,
    ): LeagueTransferOffer {
        abort_if($buyer->budget < $offeredFee, 422, 'Orçamento insuficiente para esta oferta.');
        abort_unless($listing->isOpen(), 409, 'Esta listagem não está mais disponível.');
        abort_if($listing->seller_team_id === $buyer->id, 422, 'Não é possível comprar seu próprio jogador.');

        $offer = LeagueTransferOffer::create([
            'listing_id'       => $listing->id,
            'buyer_team_id'    => $buyer->id,
            'league_player_id' => $listing->league_player_id,
            'offered_fee'      => $offeredFee,
            'offered_wage'     => $offeredWage,
            'contract_rounds'  => $contractRounds,
            'status'           => 'pending',
        ]);

        // CPU vende → resolve a Fase 1 imediatamente
        if ($listing->sellerTeam->isCpu()) {
            $this->resolveTeamDecision($offer, $listing);
        }

        return $offer->fresh();
    }

    /**
     * Fase 1: decisão do clube vendedor.
     * Chamado automaticamente para CPU ou manualmente para humano.
     */
    public function resolveTeamDecision(LeagueTransferOffer $offer, ?LeagueTransferListing $listing = null): void
    {
        $listing ??= $offer->listing;

        if ($listing->acceptsFee($offer->offered_fee)) {
            $offer->update(['status' => 'pending_player']);
            // Fase 2: jogador decide (sempre automático)
            $this->resolvePlayerDecision($offer);
        } else {
            $offer->update(['status' => 'rejected_team']);
        }
    }

    /**
     * Fase 2: decisão do jogador.
     * Sempre automática — compara o salário oferecido com o mínimo do jogador.
     */
    public function resolvePlayerDecision(LeagueTransferOffer $offer): void
    {
        $player  = $offer->listing->player ?? $offer->player;
        $minWage = $this->marketValue->minimumWage($player);

        if ($offer->offered_wage >= $minWage) {
            $offer->update(['status' => 'accepted']);
            $this->executeTransfer($offer);
        } else {
            $offer->update(['status' => 'rejected_player']);
        }
    }

    /**
     * Permite que um clube vendedor humano faça uma contraproposta.
     */
    public function counter(LeagueTransferOffer $offer, int $counterPrice): void
    {
        abort_unless($offer->isPending(), 409, 'Oferta não está mais pendente.');
        $offer->update([
            'status'        => 'countered',
            'counter_price' => $counterPrice,
        ]);
    }

    /**
     * Comprador aceita a contraproposta e atualiza o fee oferecido.
     */
    public function acceptCounter(LeagueTransferOffer $offer): void
    {
        abort_unless($offer->isCountered(), 409, 'Sem contraproposta ativa.');

        $buyer = $offer->buyerTeam;
        abort_if($buyer->budget < $offer->counter_price, 422, 'Orçamento insuficiente para a contraproposta.');

        $offer->update([
            'offered_fee' => $offer->counter_price,
            'status'      => 'pending_player',
            'counter_price' => null,
        ]);

        $this->resolvePlayerDecision($offer);
    }

    // ── Free agent ───────────────────────────────────────────────────

    /**
     * Assina um free agent diretamente (sem fee, só negociação de salário).
     * Cria a oferta e resolve a Fase 2 imediatamente.
     *
     * @return LeagueTransferOffer  (status: accepted | rejected_player)
     */
    public function signFreeAgent(
        LeagueTeam $buyer,
        LeaguePlayer $player,
        int $offeredWage,
        int $contractRounds,
    ): LeagueTransferOffer {
        abort_unless($player->isFreeAgent(), 409, 'Jogador não é um free agent.');

        $offer = LeagueTransferOffer::create([
            'listing_id'       => null,
            'buyer_team_id'    => $buyer->id,
            'league_player_id' => $player->id,
            'offered_fee'      => 0,
            'offered_wage'     => $offeredWage,
            'contract_rounds'  => $contractRounds,
            'status'           => 'pending_player',
        ]);

        $minWage = $this->marketValue->minimumWage($player);

        if ($offeredWage >= $minWage) {
            $offer->update(['status' => 'accepted']);
            $this->executeFreeAgentTransfer($offer, $player, $buyer);
        } else {
            $offer->update(['status' => 'rejected_player']);
        }

        return $offer->fresh();
    }

    // ── Execução ─────────────────────────────────────────────────────

    /**
     * Executa a transferência após ambas as fases aprovadas.
     * Roda dentro de uma transaction DB.
     */
    private function executeTransfer(LeagueTransferOffer $offer): void
    {
        $listing = $offer->listing;
        $player  = $listing->player;
        $buyer   = $offer->buyerTeam;
        $seller  = $listing->sellerTeam;

        DB::transaction(function () use ($offer, $listing, $player, $buyer, $seller) {
            $currentChampionship = $buyer->league->championships()->first();
            $currentRound = $currentChampionship?->current_round ?? 0;
            $contractUntil = $currentRound + $offer->contract_rounds;

            // Move o jogador
            $player->update([
                'league_team_id' => $buyer->id,
                'wage'           => $offer->offered_wage,
                'contract_until' => $contractUntil,
                'status'         => 'active',
            ]);

            // Débito no comprador
            $buyer->decrement('budget', $offer->offered_fee);
            $buyer->transactions()->create([
                'type'        => 'transfer_fee_out',
                'amount'      => -$offer->offered_fee,
                'description' => "Compra: {$player->name}",
                'round'       => $currentRound,
            ]);

            // Crédito no vendedor
            $seller->increment('budget', $offer->offered_fee);
            $seller->transactions()->create([
                'type'        => 'transfer_fee_in',
                'amount'      => $offer->offered_fee,
                'description' => "Venda: {$player->name} → {$buyer->name}",
                'round'       => $currentRound,
            ]);

            // Fecha a listagem
            $listing->update(['status' => 'sold']);

            // Histórico
            LeagueTransfer::create([
                'league_id'        => $buyer->league_id,
                'from_team_id'     => $seller->id,
                'to_team_id'       => $buyer->id,
                'league_player_id' => $player->id,
                'fee'              => $offer->offered_fee,
                'wage'             => $offer->offered_wage,
                'contract_until'   => $contractUntil,
                'round'            => $currentRound,
            ]);
        });
    }

    private function executeFreeAgentTransfer(
        LeagueTransferOffer $offer,
        LeaguePlayer $player,
        LeagueTeam $buyer,
    ): void {
        DB::transaction(function () use ($offer, $player, $buyer) {
            $currentChampionship = $buyer->league->championships()->first();
            $currentRound = $currentChampionship?->current_round ?? 0;
            $contractUntil = $currentRound + $offer->contract_rounds;

            $player->update([
                'league_team_id' => $buyer->id,
                'wage'           => $offer->offered_wage,
                'contract_until' => $contractUntil,
                'status'         => 'active',
            ]);

            LeagueTransfer::create([
                'league_id'        => $buyer->league_id,
                'from_team_id'     => null,
                'to_team_id'       => $buyer->id,
                'league_player_id' => $player->id,
                'fee'              => 0,
                'wage'             => $offer->offered_wage,
                'contract_until'   => $contractUntil,
                'round'            => $currentRound,
            ]);
        });
    }

    // ── Utilitários ──────────────────────────────────────────────────

    /**
     * Paga salários de todos os jogadores ativos de um time nesta rodada.
     * Chamado pelo game engine ao processar cada rodada.
     */
    public function payWages(LeagueTeam $team, int $round): void
    {
        $activePlayers = $team->players()->where('status', 'active')->get();
        $totalWage     = $activePlayers->sum('wage');

        if ($totalWage === 0) return;

        $team->decrement('budget', $totalWage);
        $team->transactions()->create([
            'type'        => 'wage_payment',
            'amount'      => -$totalWage,
            'description' => "Folha salarial — Rodada {$round} ({$activePlayers->count()} jogadores)",
            'round'       => $round,
        ]);
    }

    /**
     * Libera jogadores com contrato expirado para o mercado livre.
     * Chamado pelo game engine no início de cada rodada.
     */
    public function releaseExpiredContracts(int $leagueId, int $round): int
    {
        return LeaguePlayer::where('league_id', $leagueId)
            ->where('status', 'active')
            ->where('contract_until', '>', 0)
            ->where('contract_until', '<', $round)
            ->update([
                'status'         => 'free_agent',
                'league_team_id' => null,
                'wage'           => 0,
            ]);
    }
}
