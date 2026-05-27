<?php

namespace App\Services;

use App\Models\Championship;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\LeagueTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gera e gerencia a Copa do Brasil:
 *
 * - Formato: mata-mata, ida e volta (2 partidas por confronto)
 * - 64 times: campeões + vices dos estaduais A1 + complemento de A2
 * - 6 fases: R64 → R32 → R16 → QF → SF → Final (12 rodadas simuladas)
 * - Critério de classificação: placar agregado; empate → time com melhor seeding avança
 */
class CopaBrasilService
{
    // Nomes das fases para exibição
    const PHASE_NAMES = [
        1 => 'Primeira Fase',
        2 => 'Segunda Fase',
        3 => 'Terceira Fase',
        4 => 'Quartas de Final',
        5 => 'Semifinal',
        6 => 'Final',
    ];

    const TARGET_TEAMS = 64;

    public function __construct(
        private readonly MatchSimulator $simulator,
    ) {}

    // ── Geração da Copa ───────────────────────────────────────────────────

    /**
     * Cria a Copa do Brasil a partir dos resultados dos estaduais.
     * Deve ser chamado quando a fase estadual encerrar.
     */
    public function generate(League $league): Competition
    {
        return DB::transaction(function () use ($league) {
            $year = $league->season;

            // 1. Coleta participantes
            $participants = $this->collectParticipants($league);

            // 2. Championship template (se existir)
            $chp = Championship::where('competition_type', Championship::COMPETITION_TYPE_COPA)->first();

            // 3. Cria a competição
            $copa = Competition::create([
                'league_id'        => $league->id,
                'championship_id'  => $chp?->id,
                'name'             => $chp ? "{$chp->name} {$year}" : "Copa do Brasil {$year}",
                'slug'             => "copa-do-brasil-{$year}-" . Str::lower(Str::random(4)),
                'competition_type' => Competition::COMPETITION_TYPE_COPA,
                'division'         => null,
                'state_id'         => null,
                'format'           => Competition::FORMAT_KNOCKOUT,
                'legs'             => 'double',
                'teams_count'      => $participants->count(),
                'promotion_spots'  => null,
                'relegation_spots' => null,
                'status'           => Competition::STATUS_IN_PROGRESS,
                'current_round'    => 0,
                'total_rounds'     => $this->totalRounds($participants->count()),
                'season'           => $year,
            ]);

            // 4. Cria os CompetitionTeam records
            foreach ($participants as $lt) {
                CompetitionTeam::create([
                    'competition_id' => $copa->id,
                    'league_team_id' => $lt->id,
                    'team_id'        => $lt->team_id,
                    'name'           => $lt->name,
                ]);
            }

            // 5. Gera o bracket inicial e as partidas da primeira fase
            $copa->refresh();
            $this->generateFirstRound($copa);

            return $copa;
        });
    }

    // ── Avanço de rodada para knockout ────────────────────────────────────

    /**
     * Simula a próxima rodada do bracket da Copa.
     * Chamado pelo CompetitionRoundService quando a competição é knockout.
     *
     * @return array{cpuCount: int, liveCount: int, competitionFinished: bool}
     */
    public function advanceRound(Competition $copa, MatchSimulator $simulator): array
    {
        $nextRound = $copa->current_round + 1;

        if ($nextRound > $copa->total_rounds) {
            return ['cpuCount' => 0, 'liveCount' => 0, 'competitionFinished' => $copa->isFinished()];
        }

        $matches = $copa->matches()
            ->where('round', $nextRound)
            ->whereNotIn('status', ['finished'])
            ->with(['homeTeam.leagueTeam', 'awayTeam.leagueTeam'])
            ->get();

        if ($matches->isEmpty()) {
            return ['cpuCount' => 0, 'liveCount' => 0, 'competitionFinished' => $copa->isFinished()];
        }

        $cpuCount  = 0;
        $liveCount = 0;

        DB::transaction(function () use ($copa, $matches, $simulator, $nextRound, &$cpuCount, &$liveCount) {
            foreach ($matches as $match) {
                $isLive = ! ($match->homeTeam->leagueTeam->isCpu() && $match->awayTeam->leagueTeam->isCpu());

                if ($isLive) {
                    // Para partidas ao vivo: simplificação — simula como CPU por ora
                    // (integração com LiveMatchSimulator é Sprint 4)
                    $isLive = false;
                }

                $result = $simulator->simulate($match);

                $match->update([
                    'home_score' => $result['home_score'],
                    'away_score' => $result['away_score'],
                    'status'     => 'finished',
                    'played_at'  => now(),
                    'data'       => [
                        'home_possession'      => $result['home_possession'],
                        'away_possession'      => $result['away_possession'],
                        'home_shots'           => $result['home_shots'],
                        'away_shots'           => $result['away_shots'],
                        'home_shots_on_target' => $result['home_shots_on_target'],
                        'away_shots_on_target' => $result['away_shots_on_target'],
                        'events'               => $result['events'],
                    ],
                ]);

                $cpuCount++;
            }

            // Avança current_round
            $copa->increment('current_round');
            $copa->refresh();

            // Se esta era uma rodada de 2º tempo (round par) → resolve confrontos e cria próxima fase
            $bracketPhase    = (int) ceil($nextRound / 2);
            $isSecondLegRound = ($nextRound % 2 === 0);

            if ($isSecondLegRound) {
                $this->resolveAndAdvance($copa, $bracketPhase);
            }

            // Verifica se encerrou
            if ($copa->current_round >= $copa->total_rounds) {
                $copa->update(['status' => Competition::STATUS_FINISHED]);
            }
        });

        return [
            'cpuCount'           => $cpuCount,
            'liveCount'          => $liveCount,
            'competitionFinished' => $copa->fresh()->isFinished(),
        ];
    }

    // ── Helpers internos ──────────────────────────────────────────────────

    /**
     * Coleta os participantes da Copa:
     * 1. Campeões (1º lugar) dos A1 de cada estado
     * 2. Vice-campeões (2º lugar) dos A1
     * 3. Complemento com 3ºs e melhores A2 até chegar em TARGET_TEAMS
     */
    private function collectParticipants(League $league): Collection
    {
        $stateComps = Competition::where('league_id', $league->id)
            ->where('competition_type', Competition::COMPETITION_TYPE_STATE)
            ->where('division', Competition::DIVISION_FIRST)
            ->where('status', Competition::STATUS_FINISHED)
            ->with(['teams.leagueTeam'])
            ->get();

        $participants = collect();
        $thirdPlace   = collect();

        foreach ($stateComps as $comp) {
            $sorted = $comp->teams
                ->sortByDesc('points')
                ->sortByDesc('wins')
                ->sortByDesc(fn($t) => $t->goals_for - $t->goals_against)
                ->values();

            // Campeão e vice
            if ($sorted->count() >= 1) {
                $participants->push($sorted[0]->leagueTeam);
            }
            if ($sorted->count() >= 2) {
                $participants->push($sorted[1]->leagueTeam);
            }
            // 3º para fila de reserva
            if ($sorted->count() >= 3) {
                $thirdPlace->push($sorted[2]->leagueTeam);
            }
        }

        // Complemento com 3ºs lugares
        foreach ($thirdPlace as $lt) {
            if ($participants->count() >= self::TARGET_TEAMS) break;
            $participants->push($lt);
        }

        // Complemento com times A2
        if ($participants->count() < self::TARGET_TEAMS) {
            $a2Comps = Competition::where('league_id', $league->id)
                ->where('competition_type', Competition::COMPETITION_TYPE_STATE)
                ->where('division', Competition::DIVISION_SECOND)
                ->where('status', Competition::STATUS_FINISHED)
                ->with(['teams.leagueTeam'])
                ->get();

            foreach ($a2Comps as $comp) {
                $sorted = $comp->teams->sortByDesc('points')->values();
                foreach ($sorted as $ct) {
                    if ($participants->count() >= self::TARGET_TEAMS) break;
                    if (! $participants->contains('id', $ct->leagueTeam?->id)) {
                        $participants->push($ct->leagueTeam);
                    }
                }
            }
        }

        // Se ainda faltam times: repete times já existentes (não ideal, mas evita crash)
        // Na prática, com 27 estados o número já seria 54+ teams suficiente
        return $participants->filter()->unique('id')->values()->take(self::TARGET_TEAMS);
    }

    /**
     * Calcula quantas rodadas (simuladas) são necessárias para N times.
     * Cada "fase" do bracket = 2 rodadas (ida + volta).
     * Fases = ceil(log2(N)).
     */
    private function totalRounds(int $teamCount): int
    {
        if ($teamCount < 2) return 2;
        $phases = (int) ceil(log($teamCount, 2));
        return $phases * 2; // cada fase = ida + volta
    }

    /**
     * Gera o bracket e as partidas da primeira fase.
     * Times são embaralhados por overall (top seeds evitam top seeds no início).
     */
    private function generateFirstRound(Competition $copa): void
    {
        $teams = $copa->teams()
            ->with(['leagueTeam.team'])
            ->get()
            ->sortByDesc(fn(CompetitionTeam $ct) => $ct->leagueTeam?->team?->overall ?? 50)
            ->values();

        $n = $teams->count();
        // Pareia: seed 1 vs seed N, seed 2 vs seed N-1, ...
        $slots = [];
        $left  = 0;
        $right = $n - 1;
        $slot  = 1;

        while ($left < $right) {
            $slots[$slot] = [$teams[$left]->id, $teams[$right]->id];
            $left++;
            $right--;
            $slot++;
        }

        // Salva o bracket no JSON
        $copa->update(['bracket_data' => ['slots' => $slots, 'phase' => 1]]);

        // Cria as partidas: rodada 1 = ida, rodada 2 = volta
        foreach ($slots as $slotNum => [$homeId, $awayId]) {
            // Ida: team_a (home) vs team_b (away)
            CompetitionMatch::create([
                'competition_id' => $copa->id,
                'home_team_id'   => $homeId,
                'away_team_id'   => $awayId,
                'round'          => 1,
                'leg'            => 1,
                'bracket_slot'   => $slotNum,
                'status'         => 'pending',
            ]);

            // Volta: team_b (home) vs team_a (away)
            CompetitionMatch::create([
                'competition_id' => $copa->id,
                'home_team_id'   => $awayId,
                'away_team_id'   => $homeId,
                'round'          => 2,
                'leg'            => 2,
                'bracket_slot'   => $slotNum,
                'status'         => 'pending',
            ]);
        }
    }

    /**
     * Após a 2ª mão de uma fase, determina os vencedores de cada slot
     * e cria as partidas da próxima fase.
     */
    private function resolveAndAdvance(Competition $copa, int $completedPhase): void
    {
        $nextPhase = $completedPhase + 1;

        // Verifica se há próxima fase
        $roundsLeft = $copa->total_rounds - $copa->current_round;
        if ($roundsLeft <= 0) return;

        $leg1Round = ($completedPhase * 2) - 1;
        $leg2Round = $completedPhase * 2;

        // Busca partidas das duas mãos desta fase agrupadas por slot
        $leg1Matches = $copa->matches()
            ->where('round', $leg1Round)
            ->where('status', 'finished')
            ->get()
            ->keyBy('bracket_slot');

        $leg2Matches = $copa->matches()
            ->where('round', $leg2Round)
            ->where('status', 'finished')
            ->get()
            ->keyBy('bracket_slot');

        $winners      = [];
        $bracketData  = $copa->bracket_data ?? [];
        $slots        = $bracketData['slots'] ?? [];

        foreach ($slots as $slotNum => [$teamAId, $teamBId]) {
            $leg1 = $leg1Matches[$slotNum] ?? null;
            $leg2 = $leg2Matches[$slotNum] ?? null;

            if (! $leg1 || ! $leg2) continue;

            // Agregado: golos do team_a = leg1.home + leg2.away; golos do team_b = leg1.away + leg2.home
            $teamAGoals = ($leg1->home_score ?? 0) + ($leg2->away_score ?? 0);
            $teamBGoals = ($leg1->away_score ?? 0) + ($leg2->home_score ?? 0);

            if ($teamAGoals > $teamBGoals) {
                $winners[$slotNum] = $teamAId;
            } elseif ($teamBGoals > $teamAGoals) {
                $winners[$slotNum] = $teamBId;
            } else {
                // Empate no agregado: quem tem melhor seed avança (team_a = mais alto)
                $winners[$slotNum] = $teamAId;
            }
        }

        if (empty($winners)) return;

        // Pareia os vencedores para a próxima fase
        $winnerList  = array_values($winners);
        $newSlots    = [];
        $left        = 0;
        $right       = count($winnerList) - 1;
        $newSlotNum  = 1;

        while ($left < $right) {
            $newSlots[$newSlotNum] = [$winnerList[$left], $winnerList[$right]];
            $left++;
            $right--;
            $newSlotNum++;
        }

        // Atualiza bracket_data com os novos slots
        $bracketData['slots'] = $newSlots;
        $bracketData['phase'] = $nextPhase;
        $copa->update(['bracket_data' => $bracketData]);

        // Cria as partidas da nova fase
        $nextLeg1Round = ($nextPhase * 2) - 1;
        $nextLeg2Round = $nextPhase * 2;

        foreach ($newSlots as $slotNum => [$homeId, $awayId]) {
            CompetitionMatch::create([
                'competition_id' => $copa->id,
                'home_team_id'   => $homeId,
                'away_team_id'   => $awayId,
                'round'          => $nextLeg1Round,
                'leg'            => 1,
                'bracket_slot'   => $slotNum,
                'status'         => 'pending',
            ]);

            CompetitionMatch::create([
                'competition_id' => $copa->id,
                'home_team_id'   => $awayId,
                'away_team_id'   => $homeId,
                'round'          => $nextLeg2Round,
                'leg'            => 2,
                'bracket_slot'   => $slotNum,
                'status'         => 'pending',
            ]);
        }
    }

    /**
     * Retorna o nome da fase atual baseado no current_round.
     */
    public function phaseName(Competition $copa): string
    {
        $phase = (int) ceil(($copa->current_round + 1) / 2);
        return self::PHASE_NAMES[$phase] ?? "Fase {$phase}";
    }
}
