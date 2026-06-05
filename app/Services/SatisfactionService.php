<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\League;
use App\Models\LeagueCoach;
use App\Models\LeagueTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gerencia satisfação do clube com o técnico e executa demissões.
 *
 * Regras de variação (por partida disputada):
 *   Vitória  → +3
 *   Empate   → +1
 *   Derrota  → -5
 *
 * Limiar de demissão = LeagueTeam::firingThreshold()
 *   tolerance=10 (exigente) → threshold≈33
 *   tolerance=50 (médio)    → threshold≈20
 *   tolerance=100 (paciente)→ threshold≈5
 *
 * Fluxo de demissão:
 *   - Técnico CPU demitido → sorteio de um free agent; antigo vai para o mercado; sat reset para 50
 *   - Técnico humano demitido → time vira CPU; idem para o coach; sat reset para 50
 */
class SatisfactionService
{
    private const DELTA_WIN  =  3;
    private const DELTA_DRAW =  1;
    private const DELTA_LOSS = -5;

    // ── API pública ───────────────────────────────────────────────────────

    /**
     * Atualiza a satisfação de todos os times da liga com base nas partidas
     * disputadas na rodada que acabou de ser processada.
     *
     * @param array<string, int> $competitionRounds  Map competition_id → round recém-jogado
     */
    public function updateAfterRound(League $league, array $competitionRounds): void
    {
        if (empty($competitionRounds)) {
            return;
        }

        // Coleta todos os resultados das rodadas que acabaram
        $deltas = [];   // league_team_id → delta acumulado

        foreach ($competitionRounds as $competitionId => $round) {
            $matches = CompetitionMatch::where('competition_id', $competitionId)
                ->where('round', $round)
                ->where('status', 'finished')
                ->with(['homeTeam:id,league_team_id', 'awayTeam:id,league_team_id'])
                ->get();

            foreach ($matches as $match) {
                $homeId = $match->homeTeam?->league_team_id;
                $awayId = $match->awayTeam?->league_team_id;

                if (! $homeId || ! $awayId) {
                    continue;
                }

                $homeScore = $match->home_score ?? 0;
                $awayScore = $match->away_score ?? 0;

                if ($homeScore > $awayScore) {
                    $deltas[$homeId] = ($deltas[$homeId] ?? 0) + self::DELTA_WIN;
                    $deltas[$awayId] = ($deltas[$awayId] ?? 0) + self::DELTA_LOSS;
                } elseif ($homeScore < $awayScore) {
                    $deltas[$homeId] = ($deltas[$homeId] ?? 0) + self::DELTA_LOSS;
                    $deltas[$awayId] = ($deltas[$awayId] ?? 0) + self::DELTA_WIN;
                } else {
                    $deltas[$homeId] = ($deltas[$homeId] ?? 0) + self::DELTA_DRAW;
                    $deltas[$awayId] = ($deltas[$awayId] ?? 0) + self::DELTA_DRAW;
                }
            }
        }

        // Aplica deltas em batch
        foreach ($deltas as $leagueTeamId => $delta) {
            DB::table('league_teams')
                ->where('id', $leagueTeamId)
                ->update([
                    'satisfaction' => DB::raw(
                        "LEAST(100, GREATEST(1, satisfaction + ({$delta})))"
                    ),
                ]);
        }
    }

    /**
     * Verifica todos os times da liga e demite técnicos cuja satisfação
     * caiu abaixo do limiar do clube.
     */
    public function checkFirings(League $league): void
    {
        $leagueTeams = LeagueTeam::where('league_id', $league->id)->get();

        foreach ($leagueTeams as $leagueTeam) {
            if (! $leagueTeam->shouldFireCoach()) {
                continue;
            }

            DB::transaction(function () use ($leagueTeam, $league) {
                $this->fireCoach($leagueTeam, $league);
            });
        }
    }

    /**
     * Libera o técnico padrão de um time para o mercado da liga.
     * Chamado quando um humano assume o controle de um time.
     *
     * @param string $leagueId
     * @param string $leagueTeamId
     * @param string|null $coachId  FK do coach que estava no time
     */
    public function releaseCoachToPool(string $leagueId, string $leagueTeamId, ?string $coachId): void
    {
        if (! $coachId) {
            return;
        }

        LeagueCoach::where('league_id', $leagueId)
            ->where('league_team_id', $leagueTeamId)
            ->where('coach_id', $coachId)
            ->update(['league_team_id' => null]);
    }

    // ── Internos ──────────────────────────────────────────────────────────

    private function fireCoach(LeagueTeam $leagueTeam, League $league): void
    {
        Log::info("SatisfactionService: demitindo técnico do time {$leagueTeam->name} " .
                  "(sat={$leagueTeam->satisfaction}, threshold={$leagueTeam->firingThreshold()})");

        // 1. Move técnico atual para o mercado (se houver)
        if ($leagueTeam->coach_id) {
            LeagueCoach::where('league_id', $league->id)
                ->where('coach_id', $leagueTeam->coach_id)
                ->update(['league_team_id' => null]);
        }

        // 2. Se era um humano, remove o controle (time volta a ser CPU)
        $wasHuman = $leagueTeam->user_id !== null;
        if ($wasHuman) {
            $leagueTeam->update(['user_id' => null]);
            // TODO: criar LeagueInvitation para o usuário demitido (sprint futuro)
        }

        // 3. Atribui um free agent do pool da liga
        $newLeagueCoach = LeagueCoach::where('league_id', $league->id)
            ->whereNull('league_team_id')
            ->inRandomOrder()
            ->first();

        $newCoachId = null;

        if ($newLeagueCoach) {
            $newLeagueCoach->update(['league_team_id' => $leagueTeam->id]);
            $newCoachId = $newLeagueCoach->coach_id;
        }

        // 4. Atualiza o time: novo técnico + reset de satisfação
        $leagueTeam->update([
            'coach_id'     => $newCoachId,
            'satisfaction' => 50,
        ]);
    }
}
