<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\League;
use App\Models\LeagueCoach;
use App\Models\LeagueMember;
use App\Models\LeagueMessage;
use App\Models\LeagueTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gerencia satisfação do clube com o técnico e executa demissões.
 *
 * Variação depende de: resultado × local (casa/fora) × força relativa do adversário.
 * Força relativa = média de strength dos jogadores ativos. Diferença > 8 = significativa.
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
    // Threshold de diferença de força para considerar times distintos
    private const STRENGTH_THRESHOLD = 8;

    // Margem acima do limiar de demissão que dispara o aviso de zona crítica (a calibrar)
    private const CRITICAL_ZONE_MARGIN = 5;

    public function __construct(
        private readonly MessageService $messages,
    ) {}

    // [resultado][local][relação_de_força: stronger|equal|weaker]
    private const DELTAS = [
        'win' => [
            'home' => ['stronger' =>  8, 'equal' =>  5, 'weaker' =>  2],
            'away' => ['stronger' => 15, 'equal' => 10, 'weaker' =>  6],
        ],
        'draw' => [
            'home' => ['stronger' =>  1, 'equal' => -5, 'weaker' => -12],
            'away' => ['stronger' =>  5, 'equal' =>  1, 'weaker' =>  -5],
        ],
        'loss' => [
            'home' => ['stronger' => -10, 'equal' => -15, 'weaker' => -20],
            'away' => ['stronger' =>  -3, 'equal' =>  -7, 'weaker' => -12],
        ],
    ];

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

        $deltas = [];

        foreach ($competitionRounds as $competitionId => $round) {
            $matches = CompetitionMatch::where('competition_id', $competitionId)
                ->where('round', $round)
                ->where('status', 'finished')
                ->with(['homeTeam.leagueTeam', 'awayTeam.leagueTeam'])
                ->get();

            foreach ($matches as $match) {
                $homeLeagueTeamId = $match->homeTeam?->league_team_id;
                $awayLeagueTeamId = $match->awayTeam?->league_team_id;

                if (! $homeLeagueTeamId || ! $awayLeagueTeamId) {
                    continue;
                }

                [$homeDelta, $awayDelta] = $this->computeDeltas($match);

                $deltas[$homeLeagueTeamId] = ($deltas[$homeLeagueTeamId] ?? 0) + $homeDelta;
                $deltas[$awayLeagueTeamId] = ($deltas[$awayLeagueTeamId] ?? 0) + $awayDelta;
            }
        }

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
     * Atualiza a satisfação dos dois times de uma partida ao vivo que
     * acabou de ser finalizada (2º tempo concluído pelo humano).
     * Também verifica demissões após a atualização.
     */
    public function applyLiveMatchResult(CompetitionMatch $match, League $league): void
    {
        $homeLeagueTeamId = $match->homeTeam?->league_team_id;
        $awayLeagueTeamId = $match->awayTeam?->league_team_id;

        if (! $homeLeagueTeamId || ! $awayLeagueTeamId) {
            return;
        }

        [$homeDelta, $awayDelta] = $this->computeDeltas($match);

        foreach ([[$homeLeagueTeamId, $homeDelta], [$awayLeagueTeamId, $awayDelta]] as [$id, $delta]) {
            DB::table('league_teams')
                ->where('id', $id)
                ->update([
                    'satisfaction' => DB::raw(
                        "LEAST(100, GREATEST(1, satisfaction + ({$delta})))"
                    ),
                ]);
        }

        $this->checkFirings($league);
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
                $this->warnIfCriticalZone($leagueTeam, $league);
                continue;
            }

            DB::transaction(function () use ($leagueTeam, $league) {
                $this->fireCoach($leagueTeam, $league);
            });
        }
    }

    /**
     * Avisa o técnico humano quando a satisfação entra na zona crítica —
     * a poucos pontos do limiar de demissão (RF-GES-02).
     */
    private function warnIfCriticalZone(LeagueTeam $leagueTeam, League $league): void
    {
        if ($leagueTeam->user_id === null) {
            return;
        }

        $threshold = $leagueTeam->firingThreshold();

        if ($leagueTeam->satisfaction >= $threshold + self::CRITICAL_ZONE_MARGIN) {
            return;
        }

        $this->messages->sendToTeam(
            $leagueTeam,
            LeagueMessage::TYPE_CLUB,
            'A diretoria está impaciente',
            "A satisfação do clube caiu para {$leagueTeam->satisfaction}/100, muito perto do limiar de demissão ({$threshold}). Um resultado ruim pode custar o cargo.",
        );
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

    /**
     * Retorna [homeDelta, awayDelta] para uma partida finalizada.
     * Usa a tabela de 18 combinações: resultado × local × força relativa.
     */
    private function computeDeltas(CompetitionMatch $match): array
    {
        $homeScore = $match->home_score ?? 0;
        $awayScore = $match->away_score ?? 0;

        $result = match (true) {
            $homeScore > $awayScore => 'win',
            $homeScore < $awayScore => 'loss',
            default                 => 'draw',
        };

        $homeStrength = $this->avgStrength($match->homeTeam?->league_team_id);
        $awayStrength = $this->avgStrength($match->awayTeam?->league_team_id);
        $diff         = $homeStrength - $awayStrength;

        // Relação do adversário na perspectiva do time da casa
        $homeOpponentRel = match (true) {
            $diff < -self::STRENGTH_THRESHOLD => 'stronger',
            $diff >  self::STRENGTH_THRESHOLD => 'weaker',
            default                           => 'equal',
        };

        $awayOpponentRel = match ($homeOpponentRel) {
            'stronger' => 'weaker',
            'weaker'   => 'stronger',
            default    => 'equal',
        };

        $awayResult = match ($result) {
            'win'  => 'loss',
            'loss' => 'win',
            default => 'draw',
        };

        $homeDelta = self::DELTAS[$result]['home'][$homeOpponentRel];
        $awayDelta = self::DELTAS[$awayResult]['away'][$awayOpponentRel];

        return [$homeDelta, $awayDelta];
    }

    private function avgStrength(?string $leagueTeamId): float
    {
        if (! $leagueTeamId) {
            return 50.0;
        }

        return (float) (\App\Models\CompetitionPlayer::where('league_team_id', $leagueTeamId)
            ->where('status', 'active')
            ->avg('strength') ?? 50.0);
    }

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

        // 2. Se era um humano, remove o controle (time volta a ser CPU).
        //    O LeagueMember 'fired' mantém a liga visível no dashboard do
        //    demitido e alimenta o ciclo de convites (spec 005).
        $firedUserId = $leagueTeam->user_id;
        if ($firedUserId !== null) {
            $leagueTeam->update(['user_id' => null]);

            LeagueMember::updateOrCreate(
                ['league_id' => $league->id, 'user_id' => $firedUserId],
                [
                    'status'                    => LeagueMember::STATUS_FIRED,
                    'fired_from_league_team_id' => $leagueTeam->id,
                    'fired_at_global_round'     => $league->global_round ?? 0,
                ],
            );

            $this->messages->sendToUser(
                $league,
                $firedUserId,
                LeagueMessage::TYPE_CLUB,
                "Você foi demitido do {$leagueTeam->name}",
                'A diretoria perdeu a paciência com os resultados e encerrou seu contrato. ' .
                    'Continue acompanhando a liga pelo Escritório: a cada rodada, clubes sem técnico podem convidá-lo.',
                $leagueTeam,
            );
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
