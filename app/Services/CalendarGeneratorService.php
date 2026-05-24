<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Gera o calendário de pontos corridos (ida e volta) para uma competição.
 *
 * Algoritmo: circle method (round-robin).
 * - N times pares: N-1 rodadas por turno.
 * - N times ímpares: N rodadas por turno (uma folga por rodada).
 * - Total de rodadas (ida + volta): (N-1)*2 ou N*2.
 */
class CalendarGeneratorService
{
    /** Vagas de promoção padrão por divisão */
    const DEFAULT_PROMOTION_SPOTS  = 4;
    const DEFAULT_RELEGATION_SPOTS = 4;

    /**
     * Gera todos os CompetitionMatch da competição e actualiza seus totais.
     */
    public function generate(Competition $competition): Competition
    {
        $teamIds = $competition->teams()->pluck('id')->shuffle()->values()->toArray();
        $n       = count($teamIds);

        if ($n < 2) {
            throw new \RuntimeException("Competição «{$competition->name}» precisa de pelo menos 2 times para gerar o calendário.");
        }

        // Rodadas: (N-1)*2 se par, N*2 se ímpar (cada turno tem bye)
        $roundsPerLeg = ($n % 2 === 0) ? ($n - 1) : $n;
        $totalRounds  = $roundsPerLeg * 2; // ida + volta

        // ── Atualiza metadados da competição ────────────────────────────
        $competition->update([
            'teams_count'  => $n,
            'total_rounds' => $totalRounds,
            'promotion_spots'   => $competition->isSecondDivision() ? self::DEFAULT_PROMOTION_SPOTS : null,
            'relegation_spots'  => $competition->isFirstDivision()  ? self::DEFAULT_RELEGATION_SPOTS : null,
        ]);

        // ── Fixtures ──────────────────────────────────────────────────
        $firstLeg  = $this->buildRoundRobin($teamIds);
        $secondLeg = $this->invertLegs($firstLeg, $roundsPerLeg);

        $rows = [];
        $now  = now();

        foreach (array_merge($firstLeg, $secondLeg) as $roundIdx => $fixtures) {
            $round = $roundIdx + 1;
            $leg   = $roundIdx < $roundsPerLeg ? 1 : 2;

            foreach ($fixtures as [$homeId, $awayId]) {
                $rows[] = [
                    'id'             => (string) Str::uuid(),
                    'competition_id' => $competition->id,
                    'home_team_id'   => $homeId,
                    'away_team_id'   => $awayId,
                    'round'          => $round,
                    'leg'            => $leg,
                    'status'         => 'scheduled',
                    'home_score'     => null,
                    'away_score'     => null,
                    'winner_team_id' => null,
                    'data'           => null,
                    'scheduled_at'   => null,
                    'played_at'      => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        // Bulk insert para performance
        foreach (array_chunk($rows, 200) as $chunk) {
            CompetitionMatch::insert($chunk);
        }

        return $competition->fresh();
    }

    // ── Algoritmo round-robin (circle method) ────────────────────────────

    /**
     * Retorna array de rodadas para o turno de ida.
     *
     * @param  array<string>  $teams  IDs dos times (já embaralhados)
     * @return array<array<array<string>>>
     */
    private function buildRoundRobin(array $teams): array
    {
        $n      = count($teams);
        $hasBye = $n % 2 !== 0;

        if ($hasBye) {
            $teams[] = null;
            $n++;
        }

        $half   = $n / 2;
        $rounds = [];

        for ($round = 0; $round < $n - 1; $round++) {
            $fixtures = [];

            for ($i = 0; $i < $half; $i++) {
                $home = $teams[$i];
                $away = $teams[$n - 1 - $i];

                if ($home !== null && $away !== null) {
                    $fixtures[] = ($round % 2 === 0)
                        ? [$home, $away]
                        : [$away, $home];
                }
            }

            $rounds[] = $fixtures;

            // Rotação: mantém times[0] fixo, gira times[1..n-1]
            $fixed = array_shift($teams);
            $last  = array_pop($teams);
            array_unshift($teams, $last);
            array_unshift($teams, $fixed);
        }

        return $rounds;
    }

    /**
     * Gera o turno de volta invertendo mandante/visitante.
     *
     * @param  array<array<array<string>>>  $firstLeg
     * @param  int                          $offset
     */
    private function invertLegs(array $firstLeg, int $offset): array
    {
        return array_map(
            fn(array $round) => array_map(fn(array $m) => [$m[1], $m[0]], $round),
            $firstLeg
        );
    }
}
