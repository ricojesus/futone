<?php

namespace App\Services;

use App\Models\Championship;
use App\Models\Competition;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Avança todas as competições da fase atual de uma liga em uma rodada,
 * e cuida das transições de fase (state → copa → national).
 */
class GlobalRoundService
{
    public function __construct(
        private readonly CompetitionRoundService  $roundService,
        private readonly CalendarGeneratorService $calendar,
        private readonly CopaBrasilService        $copaBrasil,
        private readonly MatchSimulator           $simulator,
        private readonly SatisfactionService      $satisfaction,
        private readonly FinancialService         $financial,
    ) {}

    /**
     * Avança uma rodada em todas as competições ativas da fase atual.
     *
     * @return array{
     *   phase: string,
     *   competitionsAdvanced: int,
     *   liveMatchUrl: string|null,
     *   phaseCompleted: bool,
     *   nextPhase: string|null,
     * }
     */
    public function advance(League $league): array
    {
        $phase      = $league->current_phase;
        $compType   = $this->phaseToType($phase);
        $liveUrl    = null;
        $advanced   = 0;

        $competitions = $league->competitions()
            ->where('competition_type', $compType)
            ->where('status', Competition::STATUS_IN_PROGRESS)
            ->get();

        // Mapa competition_id → rodada recém-jogada (para o SatisfactionService)
        $roundsCompleted = [];

        foreach ($competitions as $competition) {
            if ($phase === League::PHASE_COPA) {
                // Copa do Brasil: bracket knockout com CopaBrasilService
                $result = $this->copaBrasil->advanceRound($competition, $this->simulator);
                $advanced++;
                // Copa não tem live match por enquanto (Sprint 4)
            } else {
                // Estaduais e Brasileirão: round-robin via CompetitionRoundService
                $roundBeforeAdvance = $competition->current_round + 1; // rodada que está sendo jogada
                $result = $this->roundService->advance($competition);
                $advanced++;

                $roundsCompleted[$competition->id] = $roundBeforeAdvance;

                // Captura URL da partida ao vivo do usuário (primeira encontrada)
                if ($liveUrl === null && isset($result['liveMatches']) && $result['liveMatches']->isNotEmpty()) {
                    $liveMatch = $result['liveMatches']->first();
                    $liveUrl   = route('matches.halftime', [$league, $competition, $liveMatch]);
                }
            }
        }

        // Atualiza satisfação e verifica demissões após processar todas as rodadas
        if (! empty($roundsCompleted)) {
            $this->satisfaction->updateAfterRound($league, $roundsCompleted);
            $this->satisfaction->checkFirings($league);
        }

        // Desconta salários semanais de todos os times da liga
        $this->financial->deductWeeklySalaries($league);

        // Verifica se a fase inteira encerrou
        $phaseCompleted = $league->competitions()
            ->where('competition_type', $compType)
            ->where('status', '!=', Competition::STATUS_FINISHED)
            ->doesntExist();

        $nextPhase = null;

        if ($phaseCompleted && $this->hasCompetitionsInPhase($league, $compType)) {
            $nextPhase = $this->transitionPhase($league);
        }

        return [
            'phase'                => $phase,
            'competitionsAdvanced' => $advanced,
            'liveMatchUrl'         => $liveUrl,
            'phaseCompleted'       => $phaseCompleted,
            'nextPhase'            => $nextPhase,
        ];
    }

    /**
     * Verifica se a liga tem algum jogo ao vivo pendente (status=halftime)
     * na fase atual. Usado para bloquear "avançar semana" durante jogo ao vivo.
     */
    public function hasPendingLive(League $league): bool
    {
        $compType = $this->phaseToType($league->current_phase);

        return \App\Models\CompetitionMatch::whereHas('competition', function ($q) use ($league, $compType) {
            $q->where('league_id', $league->id)
              ->where('competition_type', $compType);
        })->where('status', 'halftime')->exists();
    }

    // ── Transições de fase ────────────────────────────────────────────────

    /**
     * Determina qual é a próxima fase e executa a transição.
     * Retorna o nome da nova fase.
     */
    private function transitionPhase(League $league): string
    {
        return match ($league->current_phase) {
            League::PHASE_STATE    => $this->transitionToCopa($league),
            League::PHASE_COPA     => $this->transitionToNational($league),
            League::PHASE_NATIONAL => $this->finishSeason($league),
            default                => $league->current_phase,
        };
    }

    /**
     * Transição state → copa:
     * Gera a Copa do Brasil usando os resultados dos estaduais.
     * Aplica recuperação parcial de fitness (pausa entre estaduais e copa).
     */
    private function transitionToCopa(League $league): string
    {
        DB::transaction(function () use ($league) {
            // Gera o bracket da Copa do Brasil
            $copa = $this->copaBrasil->generate($league);

            // Cota de TV paga na criação da competição (spec 002)
            $this->financial->payTvQuotaFor($copa);

            // Recuperação parcial de fitness (descanso entre estaduais e copa)
            $this->applyInterPhaseRecovery($league);

            // Avança a fase da liga
            $league->update(['current_phase' => League::PHASE_COPA]);
        });

        return League::PHASE_COPA;
    }

    /**
     * Transição copa → national:
     * Cria Brasileirão Série A e Série B a partir dos teams com national_division definida.
     * Também aplica recuperação de fitness (descanso entre fases).
     */
    private function transitionToNational(League $league): string
    {
        DB::transaction(function () use ($league) {
            $year = $league->season;

            // Times com national_division definida na liga (LeagueTeam)
            $leagueTeamsByDivision = $this->getNationalLeagueTeams($league);

            $serieATeams = $leagueTeamsByDivision->get('first', collect());
            $serieBTeams = $leagueTeamsByDivision->get('second', collect());

            // Busca championship template para nacional (se existir)
            $chpSerieA = Championship::where('competition_type', Championship::COMPETITION_TYPE_NATIONAL)
                ->where('division', Championship::DIVISION_FIRST)
                ->first();

            $chpSerieB = Championship::where('competition_type', Championship::COMPETITION_TYPE_NATIONAL)
                ->where('division', Championship::DIVISION_SECOND)
                ->first();

            if ($serieATeams->count() >= 2) {
                $compA = Competition::create([
                    'league_id'        => $league->id,
                    'championship_id'  => $chpSerieA?->id,
                    'name'             => $chpSerieA ? "{$chpSerieA->name} {$year}" : "Brasileirão Série A {$year}",
                    'slug'             => "brasileiro-serie-a-{$year}-" . Str::lower(Str::random(4)),
                    'competition_type' => Competition::COMPETITION_TYPE_NATIONAL,
                    'division'         => Competition::DIVISION_FIRST,
                    'state_id'         => null,
                    'format'           => 'league',
                    'legs'             => 'double',
                    'teams_count'      => $serieATeams->count(),
                    'promotion_spots'  => null,
                    'relegation_spots' => 4,
                    'status'           => Competition::STATUS_IN_PROGRESS,
                    'current_round'    => 0,
                    'total_rounds'     => null,
                    'season'           => $year,
                ]);

                foreach ($serieATeams as $lt) {
                    CompetitionTeam::create([
                        'competition_id' => $compA->id,
                        'league_team_id' => $lt->id,
                        'team_id'        => $lt->team_id,
                        'name'           => $lt->name,
                    ]);
                }

                $this->calendar->generate($compA);
                $this->financial->payTvQuotaFor($compA);
            }

            if ($serieBTeams->count() >= 2) {
                $compB = Competition::create([
                    'league_id'        => $league->id,
                    'championship_id'  => $chpSerieB?->id,
                    'name'             => $chpSerieB ? "{$chpSerieB->name} {$year}" : "Brasileirão Série B {$year}",
                    'slug'             => "brasileiro-serie-b-{$year}-" . Str::lower(Str::random(4)),
                    'competition_type' => Competition::COMPETITION_TYPE_NATIONAL,
                    'division'         => Competition::DIVISION_SECOND,
                    'state_id'         => null,
                    'format'           => 'league',
                    'legs'             => 'double',
                    'teams_count'      => $serieBTeams->count(),
                    'promotion_spots'  => 4,
                    'relegation_spots' => 4,
                    'status'           => Competition::STATUS_IN_PROGRESS,
                    'current_round'    => 0,
                    'total_rounds'     => null,
                    'season'           => $year,
                ]);

                foreach ($serieBTeams as $lt) {
                    CompetitionTeam::create([
                        'competition_id' => $compB->id,
                        'league_team_id' => $lt->id,
                        'team_id'        => $lt->team_id,
                        'name'           => $lt->name,
                    ]);
                }

                $this->calendar->generate($compB);
                $this->financial->payTvQuotaFor($compB);
            }

            // Recuperação completa de fitness entre fases (descanso)
            $this->applyInterPhaseRecovery($league);

            // Avança a fase
            $league->update(['current_phase' => League::PHASE_NATIONAL]);
        });

        return League::PHASE_NATIONAL;
    }

    /**
     * "Transição" nacional → fim de temporada.
     * Apenas sinaliza que a temporada terminou (o resumo já existe).
     */
    private function finishSeason(League $league): string
    {
        // Nada a criar — o banner de "Temporada encerrada" aparece automaticamente
        // quando todas as competitions estão com status=finished.
        return 'finished';
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function phaseToType(string $phase): string
    {
        return match ($phase) {
            League::PHASE_STATE    => Competition::COMPETITION_TYPE_STATE,
            League::PHASE_COPA     => Competition::COMPETITION_TYPE_COPA,
            League::PHASE_NATIONAL => Competition::COMPETITION_TYPE_NATIONAL,
            default                => Competition::COMPETITION_TYPE_STATE,
        };
    }

    private function hasCompetitionsInPhase(League $league, string $compType): bool
    {
        return $league->competitions()
            ->where('competition_type', $compType)
            ->exists();
    }

    /**
     * Retorna LeagueTeams da liga agrupados por national_division (first/second).
     * A divisão vem do PRÓPRIO LeagueTeam — atualizada pelas viradas de temporada
     * com promoções/rebaixamentos (spec 002) — nunca do catálogo mestre.
     *
     * @return Collection<string, Collection<LeagueTeam>>
     */
    private function getNationalLeagueTeams(League $league): Collection
    {
        return LeagueTeam::where('league_id', $league->id)
            ->whereIn('national_division', ['first', 'second'])
            ->get()
            ->groupBy('national_division');
    }

    /**
     * Entre fases o time descansa — recuperação parcial de fitness para todos.
     */
    private function applyInterPhaseRecovery(League $league): void
    {
        $leagueTeamIds = LeagueTeam::where('league_id', $league->id)->pluck('id');

        CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)
            ->where('fitness', '<', 100)
            ->get()
            ->each(function (CompetitionPlayer $p) {
                $stamina   = max(1, $p->stamina ?? 50);
                $recovery  = (int) round(rand(20, 35) * ($stamina / 90));
                $newFitness = min(100, $p->fitness + $recovery);
                $p->update(['fitness' => $newFitness]);
            });
    }
}
