<?php

namespace App\Services;

use App\Models\CompetitionMatch;
use App\Models\MatchState;
use Illuminate\Support\Collection;

/**
 * Motor CPU × Humano — simula a partida em duas metades.
 *
 * Fluxo:
 *   1. simulateFirstHalf()  → roda os plays 1-45, persiste estado no intervalo
 *   2. [técnico faz substituições / ajustes táticos]
 *   3. simulateSecondHalf() → relê escalação atualizada, roda plays 46-90, finaliza partida
 */
class LiveMatchSimulator
{
    use SimulatesMatch;

    private const HALFTIME_PLAY = 45;

    public function __construct(
        private readonly MatchNarrator $narrator = new MatchNarrator(),
    ) {}

    // ── API pública ──────────────────────────────────────────────────

    /**
     * Simula o primeiro tempo (plays 1–45) e persiste o estado do intervalo.
     * Marca a partida com status 'halftime'.
     */
    public function simulateFirstHalf(CompetitionMatch $match): MatchState
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

        $this->runPlays(1, self::HALFTIME_PLAY, $state);

        // Adiciona evento de intervalo na narração
        $state['events'][] = [
            'type'      => 'halftime',
            'play'      => self::HALFTIME_PLAY,
            'narration' => 'Apita para o intervalo! ' .
                           "{$state['homeTeamName']} {$state['homeScore']} × {$state['awayScore']} {$state['awayTeamName']}.",
        ];

        // Persiste estado serializado (inclui metadados para coordenação Human×Human)
        $serialized                       = $this->serializeState($state);
        $serialized['halftime_at']        = now()->toIso8601String();
        $serialized['home_ready']         = false;
        $serialized['away_ready']         = false;
        $serialized['home_substitutions'] = [];
        $serialized['away_substitutions'] = [];

        $matchState = MatchState::updateOrCreate(
            ['competition_match_id' => $match->id],
            ['state' => $serialized],
        );

        $match->update(['status' => 'halftime']);

        return $matchState;
    }

    /**
     * Simula o segundo tempo (plays 46–90) reutilizando o estado do intervalo.
     * Relê a escalação para capturar substituições feitas no intervalo.
     * Finaliza e persiste o resultado completo na partida.
     */
    public function simulateSecondHalf(CompetitionMatch $match): array
    {
        $matchState = MatchState::where('competition_match_id', $match->id)->firstOrFail();
        $state      = $this->deserializeState($matchState->state);

        // Recarrega escalações para aplicar substituições do intervalo
        $homeData = $this->loadLineup($match->homeTeam, $match->round ?? 0);
        $awayData = $this->loadLineup($match->awayTeam, $match->round ?? 0);

        $state['home']          = $homeData['players'];
        $state['away']          = $awayData['players'];
        $state['homeFormation'] = $homeData['formation'];
        $state['awayFormation'] = $awayData['formation'];

        $this->runPlays(self::HALFTIME_PLAY + 1, self::PLAYS, $state);

        $result = $this->buildResult($state);

        // Salva resultado final na partida
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
                'live'                 => true, // flag: partida foi disputada com técnico humano
            ],
        ]);

        // Remove estado intermediário — não é mais necessário
        $matchState->delete();

        return $result;
    }

    // ── Serialização do estado do intervalo ─────────────────────────

    /**
     * Converte o estado (com Collections) em array puro para persistência JSON.
     */
    private function serializeState(array $state): array
    {
        return [
            'home'              => $state['home']->values()->all(),
            'away'              => $state['away']->values()->all(),
            'homeFormation'     => $state['homeFormation'],
            'awayFormation'     => $state['awayFormation'],
            'homeTeamName'      => $state['homeTeamName'],
            'awayTeamName'      => $state['awayTeamName'],
            'sector'            => $state['sector'],
            'possession'        => $state['possession'],
            'homeScore'         => $state['homeScore'],
            'awayScore'         => $state['awayScore'],
            'homePossCount'     => $state['homePossCount'],
            'homeShots'         => $state['homeShots'],
            'awayShots'         => $state['awayShots'],
            'homeShotsOnTarget' => $state['homeShotsOnTarget'],
            'awayShotsOnTarget' => $state['awayShotsOnTarget'],
            'events'            => $state['events'],
        ];
    }

    /**
     * Reconstrói o estado (com Collections) a partir do JSON persistido.
     */
    private function deserializeState(array $persisted): array
    {
        return array_merge($persisted, [
            'home' => collect($persisted['home']),
            'away' => collect($persisted['away']),
        ]);
    }
}
