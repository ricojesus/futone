<?php

namespace App\Services;

use App\Models\CompetitionMatch;

/**
 * Motor CPU × CPU — simula a partida inteira de uma vez.
 * Toda a lógica de simulação vive no trait SimulatesMatch.
 */
class MatchSimulator
{
    use SimulatesMatch;

    public function __construct(
        private readonly MatchNarrator $narrator = new MatchNarrator(),
        private readonly CpuLineupService $cpuLineups = new CpuLineupService(),
    ) {}

    /**
     * Simula uma partida completa e retorna o resultado.
     */
    public function simulate(CompetitionMatch $match): array
    {
        $homeData = $this->loadLineup($match->homeTeam, $match->round ?? 0);
        $awayData = $this->loadLineup($match->awayTeam, $match->round ?? 0);

        $state = $this->initialState(
            $match->homeTeam->name,
            $match->awayTeam->name,
            $homeData['players'],
            $awayData['players'],
            $homeData['formation'],
            $awayData['formation'],
        );

        $this->runPlays(1, self::PLAYS, $state);

        return $this->buildResult($state);
    }

    /**
     * Simula e persiste o resultado direto no CompetitionMatch.
     */
    public function simulateAndSave(CompetitionMatch $match): CompetitionMatch
    {
        $result   = $this->simulate($match);
        $winnerId = null;

        if ($result['home_score'] !== $result['away_score']) {
            $winnerId = $result['home_score'] > $result['away_score']
                ? $match->home_team_id
                : $match->away_team_id;
        }

        $match->update([
            'home_score'     => $result['home_score'],
            'away_score'     => $result['away_score'],
            'winner_team_id' => $winnerId,
            'status'         => 'finished',
            'played_at'      => now(),
            'data'           => [
                'home_possession'      => $result['home_possession'],
                'away_possession'      => $result['away_possession'],
                'home_shots'           => $result['home_shots'],
                'away_shots'           => $result['away_shots'],
                'home_shots_on_target' => $result['home_shots_on_target'],
                'away_shots_on_target' => $result['away_shots_on_target'],
                'home_formation'       => $result['home_formation'],
                'away_formation'       => $result['away_formation'],
                'events'               => $result['events'],
            ],
        ]);

        return $match->fresh();
    }
}
