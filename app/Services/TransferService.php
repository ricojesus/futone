<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTeam;
use App\Models\CompetitionTransaction;
use App\Models\CompetitionTransfer;
use App\Models\CompetitionTransferOffer;
use App\Models\LeagueTeam;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function __construct(
        private readonly MarketValueService $marketValue,
    ) {}

    // ── Validações de elenco ─────────────────────────────────────────────

    public function canSell(LeagueTeam $team, CompetitionPlayer $player): bool
    {
        $total = CompetitionPlayer::where('league_team_id', $team->id)
            ->where('status', 'active')->count();

        if ($total <= 15) return false;

        if ($player->position === 'goalkeeper') {
            $gks = CompetitionPlayer::where('league_team_id', $team->id)
                ->where('status', 'active')
                ->where('position', 'goalkeeper')->count();
            if ($gks <= 2) return false;
        }

        return true;
    }

    public function canBuy(LeagueTeam $team): bool
    {
        $total = CompetitionPlayer::where('league_team_id', $team->id)
            ->where('status', 'active')->count();

        return $total < 25;
    }

    public function isInMinimumContract(CompetitionPlayer $player): bool
    {
        if (! $player->joined_at) return false;

        return now()->lt($player->joined_at->addMonths(6));
    }

    // ── Oferta direta (sem listagem prévia) ──────────────────────────────

    /**
     * Técnico humano faz uma proposta por um jogador de outro time.
     * Cria a oferta com listing_id = null (oferta direta).
     */
    public function makeDirectOffer(
        CompetitionTeam $buyerCompTeam,
        CompetitionPlayer $player,
        int $offeredFee,
        int $offeredWage,
        int $contractYears,
    ): CompetitionTransferOffer {
        $buyerLeagueTeam = $buyerCompTeam->leagueTeam;

        abort_if($buyerLeagueTeam->budget < $offeredFee, 422, 'Orçamento insuficiente para esta oferta.');
        abort_if($player->league_team_id === $buyerLeagueTeam->id, 422, 'Não é possível fazer proposta para o próprio jogador.');
        abort_if($this->isInMinimumContract($player), 422, 'Jogador ainda está no período mínimo de contrato.');
        abort_unless($this->canBuy($buyerLeagueTeam), 422, 'Elenco cheio. Máximo de 25 jogadores atingido.');

        $offer = CompetitionTransferOffer::create([
            'listing_id'           => null,
            'buyer_team_id'        => $buyerCompTeam->id,
            'competition_player_id' => $player->id,
            'offered_fee'          => $offeredFee,
            'offered_wage'         => $offeredWage,
            'contract_rounds'      => $contractYears,
            'status'               => 'pending',
        ]);

        // Se o time vendedor for CPU, resolve a Fase 1 automaticamente
        $sellerLeagueTeam = LeagueTeam::find($player->league_team_id);
        if ($sellerLeagueTeam?->isCpu()) {
            $this->resolveTeamDecision($offer);
        }

        return $offer->fresh();
    }

    /**
     * Assina um jogador sem clube (free agent).
     */
    public function signFreeAgent(
        CompetitionTeam $buyerCompTeam,
        CompetitionPlayer $player,
        int $offeredWage,
        int $contractYears,
    ): CompetitionTransferOffer {
        abort_unless($player->isFreeAgent(), 409, 'Jogador não é um free agent.');
        abort_unless($this->canBuy($buyerCompTeam->leagueTeam), 422, 'Elenco cheio. Máximo de 25 jogadores atingido.');

        $offer = CompetitionTransferOffer::create([
            'listing_id'            => null,
            'buyer_team_id'         => $buyerCompTeam->id,
            'competition_player_id' => $player->id,
            'offered_fee'           => 0,
            'offered_wage'          => $offeredWage,
            'contract_rounds'       => $contractYears,
            'status'                => 'pending_player',
        ]);

        $this->resolvePlayerDecision($offer);

        return $offer->fresh();
    }

    // ── Fase 1: decisão do clube vendedor ────────────────────────────────

    /**
     * Resolve a decisão do clube vendedor (sempre CPU neste ponto).
     * CPU aceita se o fee for razoável em relação ao market_value.
     */
    public function resolveTeamDecision(CompetitionTransferOffer $offer): void
    {
        $player    = $offer->player;
        $minFee    = (int) ($player->market_value * 0.7);

        if ($offer->offered_fee >= $minFee) {
            $sellerLeagueTeamId = $player->league_team_id;
            abort_unless(
                $this->canSell(LeagueTeam::find($sellerLeagueTeamId), $player),
                422, 'Time vendedor não pode vender: elenco abaixo do mínimo.'
            );
            $offer->update(['status' => 'pending_player']);
            $this->resolvePlayerDecision($offer);
        } else {
            $offer->update(['status' => 'rejected_team']);
        }
    }

    // ── Fase 2: decisão do jogador (sistema de pontuação) ───────────────

    public function resolvePlayerDecision(CompetitionTransferOffer $offer): void
    {
        $player       = $offer->player;
        $buyerCompTeam = $offer->buyerTeam;
        $offeredWage  = $offer->offered_wage;

        $minWage = $this->minimumWage($player);

        if ($offeredWage < $minWage) {
            $offer->update(['status' => 'rejected_player']);
            return;
        }

        $score = $this->playerScore($player, $buyerCompTeam, $offeredWage);

        if ($score >= 4) {
            $offer->update(['status' => 'accepted']);
            $this->executeTransfer($offer);
        } elseif ($score >= 1) {
            // Counter-proposta: pede salário 30% maior que o atual
            $counterWage = (int) ($player->wage * 1.3);
            $offer->update(['status' => 'countered', 'counter_price' => $counterWage]);
        } else {
            $offer->update(['status' => 'rejected_player']);
        }
    }

    /**
     * Técnico humano do time vendedor aceita perder o jogador mas
     * oferece aumento de salário para retê-lo (resposta à contra-proposta).
     */
    public function retentionOffer(CompetitionTransferOffer $offer, int $retentionWage): void
    {
        abort_unless($offer->status === 'countered', 409, 'Oferta não está em estado de contra-proposta.');

        // Atualiza o salário do jogador e reavalia
        $offer->player->update(['wage' => $retentionWage]);

        $offeredWage = $offer->offered_wage;
        $minWage     = $this->minimumWage($offer->player->fresh());

        if ($retentionWage >= $minWage && $offeredWage >= (int) ($retentionWage * 1.15)) {
            // Salário de retenção não é suficientemente melhor que a proposta → jogador vai
            $offer->update(['status' => 'accepted']);
            $this->executeTransfer($offer);
        } else {
            // Jogador prefere ficar
            $offer->update(['status' => 'rejected_player']);
        }
    }

    // ── Pontuação de decisão do jogador ─────────────────────────────────

    private function playerScore(
        CompetitionPlayer $player,
        CompetitionTeam   $buyerCompTeam,
        int               $offeredWage,
    ): int {
        $score = 0;

        // 1. Salário oferecido vs. atual
        $currentWage = max(1, $player->wage);
        $ratio = $offeredWage / $currentWage;

        $score += match (true) {
            $ratio >= 1.5 =>  3,
            $ratio >= 1.2 =>  1,
            $ratio >= 1.0 => -1,
            default       => -3,
        };

        // 2. Divisão do comprador vs. atual
        $buyerDiv   = $this->primaryDivision($buyerCompTeam->leagueTeam);
        $sellerDiv  = $player->league_team_id
            ? $this->primaryDivision(LeagueTeam::find($player->league_team_id))
            : 'none';

        $divWeight = ['first' => 2, 'second' => 1, 'none' => 0];
        $divDiff   = ($divWeight[$buyerDiv] ?? 0) - ($divWeight[$sellerDiv] ?? 0);

        $score += match (true) {
            $divDiff > 0  =>  2,
            $divDiff < 0  => -3,
            default       =>  0,
        };

        // 3. Posição do time comprador na tabela
        $buyerRank = $this->tableRank($buyerCompTeam);
        $score += match (true) {
            $buyerRank === 'title'     =>  2,
            $buyerRank === 'top_half'  =>  1,
            $buyerRank === 'relegation'=> -2,
            default                    =>  0,
        };

        // 4. Posição do time atual na tabela
        $sellerCompTeam = $player->league_team_id
            ? $this->primaryCompetitionTeam(LeagueTeam::find($player->league_team_id))
            : null;
        $sellerRank = $sellerCompTeam ? $this->tableRank($sellerCompTeam) : 'mid_half';

        $score += match ($sellerRank) {
            'title'     => -2,
            'top_half'  => -1,
            'relegation' =>  1,
            default      =>  0,
        };

        // 5. Titular ou reserva
        $isStarter = $this->isStarter($player);
        $score += $isStarter ? -1 : 2;

        // 6. Idade
        $score += match (true) {
            $player->age <= 26 =>  1,
            $player->age <= 30 =>  0,
            default            => -1,
        };

        // 7. Free agent
        if ($player->isFreeAgent()) {
            $score += 3;
        }

        return $score;
    }

    // ── Execução da transferência ────────────────────────────────────────

    private function executeTransfer(CompetitionTransferOffer $offer): void
    {
        $player        = $offer->player;
        $buyerCompTeam = $offer->buyerTeam;
        $buyerLeagueTeam = $buyerCompTeam->leagueTeam;
        $sellerLeagueTeam = $player->league_team_id
            ? LeagueTeam::find($player->league_team_id)
            : null;

        abort_unless($this->canBuy($buyerLeagueTeam), 422, 'Elenco cheio.');
        if ($sellerLeagueTeam) {
            abort_unless($this->canSell($sellerLeagueTeam, $player), 422, 'Elenco do vendedor abaixo do mínimo.');
        }

        DB::transaction(function () use ($offer, $player, $buyerCompTeam, $buyerLeagueTeam, $sellerLeagueTeam) {
            $fee           = $offer->offered_fee;
            $wage          = $offer->offered_wage;
            $contractUntil = date('Y') + max(1, (int) $offer->contract_rounds);
            $competition   = $buyerCompTeam->competition;
            $round         = $competition?->current_round ?? 0;

            // Move jogador
            $player->update([
                'league_team_id' => $buyerLeagueTeam->id,
                'wage'           => $wage,
                'contract_until' => $contractUntil,
                'joined_at'      => now(),
                'status'         => 'active',
            ]);

            // Débito no comprador
            if ($fee > 0) {
                $buyerLeagueTeam->decrement('budget', $fee);

                if ($competition) {
                    CompetitionTransaction::create([
                        'competition_team_id' => $buyerCompTeam->id,
                        'type'                => 'transfer_fee_out',
                        'amount'              => -$fee,
                        'description'         => "Compra: {$player->name}",
                        'round'               => $round,
                    ]);
                }
            }

            // Crédito no vendedor
            if ($fee > 0 && $sellerLeagueTeam) {
                $sellerLeagueTeam->increment('budget', $fee);

                $sellerCompTeam = $this->primaryCompetitionTeam($sellerLeagueTeam);
                if ($sellerCompTeam) {
                    CompetitionTransaction::create([
                        'competition_team_id' => $sellerCompTeam->id,
                        'type'                => 'transfer_fee_in',
                        'amount'              => $fee,
                        'description'         => "Venda: {$player->name}",
                        'round'               => $round,
                    ]);
                }
            }

            // Histórico
            if ($competition) {
                CompetitionTransfer::create([
                    'competition_id'        => $competition->id,
                    'from_team_id'          => $sellerLeagueTeam
                        ? $this->primaryCompetitionTeam($sellerLeagueTeam)?->id
                        : null,
                    'to_team_id'            => $buyerCompTeam->id,
                    'competition_player_id' => $player->id,
                    'fee'                   => $fee,
                    'wage'                  => $wage,
                    'contract_until'        => $contractUntil,
                    'round'                 => $round,
                ]);
            }

            $offer->update(['status' => 'accepted']);
        });
    }

    // ── Helpers privados ─────────────────────────────────────────────────

    private function minimumWage(CompetitionPlayer $player): int
    {
        if ($player->isFreeAgent()) {
            $base = $this->marketValue->suggestedWage($player->market_value);
            return (int) round($base * ($player->wage_expectation_factor ?? 1.0));
        }

        return (int) ($player->wage * 1.15);
    }

    private function primaryDivision(LeagueTeam $leagueTeam): string
    {
        $comp = $this->primaryCompetitionTeam($leagueTeam)?->competition;

        return match ($comp?->competition_type) {
            Competition::COMPETITION_TYPE_NATIONAL => $comp->division ?? 'second',
            Competition::COMPETITION_TYPE_STATE    => $comp->division ?? 'second',
            default                                => 'none',
        };
    }

    public function primaryCompetitionTeamPublic(LeagueTeam $leagueTeam): ?CompetitionTeam
    {
        return $this->primaryCompetitionTeam($leagueTeam);
    }

    public function executeTransferPublic(CompetitionTransferOffer $offer): void
    {
        $this->executeTransfer($offer);
    }

    private function primaryCompetitionTeam(LeagueTeam $leagueTeam): ?CompetitionTeam
    {
        // Retorna a CompetitionTeam de maior prestígio (nacional > estadual)
        return CompetitionTeam::whereHas('competition', fn($q) =>
                $q->where('league_id', $leagueTeam->league_id)
                  ->whereIn('status', [Competition::STATUS_IN_PROGRESS, Competition::STATUS_WAITING])
            )
            ->where('league_team_id', $leagueTeam->id)
            ->with('competition')
            ->get()
            ->sortByDesc(fn($ct) => match($ct->competition?->competition_type) {
                Competition::COMPETITION_TYPE_NATIONAL => 3,
                Competition::COMPETITION_TYPE_COPA     => 2,
                Competition::COMPETITION_TYPE_STATE    => 1,
                default                                => 0,
            })
            ->first();
    }

    private function tableRank(CompetitionTeam $compTeam): string
    {
        $competition = $compTeam->competition;
        if (! $competition) return 'mid_half';

        $teams = CompetitionTeam::where('competition_id', $competition->id)
            ->orderByDesc('points')
            ->orderByDesc(DB::raw('goals_for - goals_against'))
            ->pluck('id')
            ->values();

        $total    = $teams->count();
        $position = $teams->search($compTeam->id) + 1;

        if ($position <= 3) return 'title';
        if ($position <= ceil($total / 2)) return 'top_half';
        if ($position > $total - 3) return 'relegation';
        return 'mid_half';
    }

    private function isStarter(CompetitionPlayer $player): bool
    {
        return \App\Models\CompetitionLineupPlayer::where('competition_player_id', $player->id)
            ->where('is_starter', true)
            ->exists();
    }
}
