<?php

namespace App\Services;

use App\Models\CompetitionTeam;
use App\Models\LeagueTeam;
use Illuminate\Support\Collection;

/**
 * Trait com toda a lógica primitiva de simulação de partida.
 * Compartilhado entre MatchSimulator (CPU×CPU) e LiveMatchSimulator (CPU×Humano).
 */
trait SimulatesMatch
{
    // ── Constantes ───────────────────────────────────────────────────

    private const PLAYS       = 90;
    private const LUCK_SECTOR = 0.15;
    private const LUCK_SHOT   = 0.20;

    private const SECTOR_WEIGHTS = [
        1 => ['goalkeeper' => 1.50, 'defender' => 1.00, 'midfielder' => 0.20, 'forward' => 0.00],
        2 => ['goalkeeper' => 0.00, 'defender' => 1.00, 'midfielder' => 0.60, 'forward' => 0.10],
        3 => ['goalkeeper' => 0.00, 'defender' => 0.30, 'midfielder' => 1.00, 'forward' => 0.30],
        4 => ['goalkeeper' => 0.00, 'defender' => 0.10, 'midfielder' => 0.60, 'forward' => 1.00],
        5 => ['goalkeeper' => 0.00, 'defender' => 0.00, 'midfielder' => 0.20, 'forward' => 1.50],
    ];

    // ── Estado inicial ───────────────────────────────────────────────

    /**
     * Monta o estado inicial da partida a partir das escalações carregadas.
     */
    protected function initialState(
        string     $homeTeamName,
        string     $awayTeamName,
        Collection $homePlayers,
        Collection $awayPlayers,
        string     $homeFormation,
        string     $awayFormation,
    ): array {
        return [
            'home'               => $homePlayers,
            'away'               => $awayPlayers,
            'homeFormation'      => $homeFormation,
            'awayFormation'      => $awayFormation,
            'homeTeamName'       => $homeTeamName,
            'awayTeamName'       => $awayTeamName,
            'sector'             => 3,
            'possession'         => random_int(0, 1) ? 'home' : 'away',
            'homeScore'          => 0,
            'awayScore'          => 0,
            'homePossCount'      => 0,
            'homeShots'          => 0,
            'awayShots'          => 0,
            'homeShotsOnTarget'  => 0,
            'awayShotsOnTarget'  => 0,
            'events'             => [],
        ];
    }

    /**
     * Empacota o estado simulado no formato de resultado padrão.
     */
    protected function buildResult(array $state): array
    {
        return [
            'home_score'           => $state['homeScore'],
            'away_score'           => $state['awayScore'],
            'home_possession'      => (int) round($state['homePossCount'] / self::PLAYS * 100),
            'away_possession'      => 100 - (int) round($state['homePossCount'] / self::PLAYS * 100),
            'home_shots'           => $state['homeShots'],
            'away_shots'           => $state['awayShots'],
            'home_shots_on_target' => $state['homeShotsOnTarget'],
            'away_shots_on_target' => $state['awayShotsOnTarget'],
            'home_formation'       => $state['homeFormation'],
            'away_formation'       => $state['awayFormation'],
            'events'               => $state['events'],
        ];
    }

    // ── Loop de simulação ────────────────────────────────────────────

    /**
     * Executa as jogadas de $from até $to (inclusive), modificando $state in-place.
     *
     * $totalPlays é sempre 90 — usado para calcular playsLeft e urgência tática
     * de forma correta mesmo quando o loop começa no minuto 46 (segundo tempo).
     */
    protected function runPlays(int $from, int $to, array &$state): void
    {
        for ($play = $from; $play <= $to; $play++) {
            if ($state['possession'] === 'home') {
                $state['homePossCount']++;
            }

            $playsLeft = self::PLAYS - $play;
            $scoreDiff = $state['possession'] === 'home'
                ? $state['homeScore'] - $state['awayScore']
                : $state['awayScore'] - $state['homeScore'];

            $narratorCtx = $this->buildNarratorContext(
                $state['possession'],
                $state['homeTeamName'],
                $state['awayTeamName'],
                $play,
                $state['homeScore'],
                $state['awayScore'],
            );

            $advance      = $this->shouldAdvance($scoreDiff, $playsLeft, $state['sector'], $state['possession']);
            $targetSector = $advance
                ? ($state['possession'] === 'home' ? $state['sector'] + 1 : $state['sector'] - 1)
                : $state['sector'];

            // ── Chegou à área: chute! ────────────────────────────────
            if ($advance && ($targetSector > 5 || $targetSector < 1)) {
                $shot = $this->resolveShot($state['home'], $state['away'], $state['possession']);

                if ($state['possession'] === 'home') {
                    $state['homeShots']++;
                    if ($shot['on_target']) $state['homeShotsOnTarget']++;
                } else {
                    $state['awayShots']++;
                    if ($shot['on_target']) $state['awayShotsOnTarget']++;
                }

                if ($shot['goal']) {
                    if ($state['possession'] === 'home') $state['homeScore']++;
                    else $state['awayScore']++;

                    $state['events'][] = [
                        'type'        => 'goal',
                        'team'        => $state['possession'],
                        'play'        => $play,
                        'scorer_id'   => $shot['scorer_id'],
                        'scorer_name' => $shot['scorer_name'],
                        'narration'   => $this->narrator->narrate('goal', array_merge(
                            $narratorCtx,
                            [
                                'home_score'  => $state['homeScore'],
                                'away_score'  => $state['awayScore'],
                                'scorer_name' => $shot['scorer_name'],
                            ]
                        )),
                    ];

                    $state['sector']     = 3;
                    $state['possession'] = $state['possession'] === 'home' ? 'away' : 'home';

                } elseif ($shot['on_target']) {
                    $state['events'][] = [
                        'type'      => 'shot_saved',
                        'team'      => $state['possession'],
                        'play'      => $play,
                        'narration' => $this->narrator->narrate('shot_saved', $narratorCtx),
                    ];

                    $state['sector']     = $state['possession'] === 'home' ? 1 : 5;
                    $state['possession'] = $state['possession'] === 'home' ? 'away' : 'home';

                } else {
                    $state['events'][] = [
                        'type'      => 'shot_missed',
                        'team'      => $state['possession'],
                        'play'      => $play,
                        'narration' => $this->narrator->narrate('shot_missed', $narratorCtx),
                    ];

                    $state['sector']     = $state['possession'] === 'home' ? 1 : 5;
                    $state['possession'] = $state['possession'] === 'home' ? 'away' : 'home';
                }

                continue;
            }

            // ── Disputa de posse no setor alvo ──────────────────────
            $targetSector = max(1, min(5, $targetSector));
            $contest      = $this->resolveSector(
                $state['home'], $state['away'],
                $targetSector, $state['possession'],
                $state['homeFormation'], $state['awayFormation'],
            );

            $state['sector'] = $targetSector;

            if ($contest['possession_change']) {
                $loser               = $state['possession'];
                $state['possession'] = $state['possession'] === 'home' ? 'away' : 'home';

                if ($this->narrator->isCounterAttack($state['sector'], $state['possession'])) {
                    $state['events'][] = $this->narrateKeyMoment(
                        'counter_attack', $state['possession'], $play,
                        $this->buildNarratorContext(
                            $state['possession'],
                            $state['homeTeamName'], $state['awayTeamName'],
                            $play, $state['homeScore'], $state['awayScore'],
                        )
                    );
                } elseif ($this->narrator->shouldNarrateLoss($state['sector'], $loser)) {
                    $state['events'][] = $this->narrateKeyMoment(
                        'possession_lost_attack', $loser, $play, $narratorCtx
                    );
                }
            } else {
                if ($advance && $this->narrator->shouldNarrateAdvance($targetSector, $state['possession'])) {
                    $situation = $this->advanceSituation($targetSector, $state['possession'], $playsLeft, $scoreDiff);
                    if ($situation) {
                        $state['events'][] = $this->narrateKeyMoment($situation, $state['possession'], $play, $narratorCtx);
                    }
                }
            }
        }
    }

    // ── Carregamento de escalação ────────────────────────────────────

    protected function loadLineup(CompetitionTeam $compTeam, int $round): array
    {
        $leagueTeam = $compTeam->leagueTeam;

        $lineup = $leagueTeam->lineups()
            ->where('status', 'active')
            ->whereIn('round', [$round, 0])
            ->orderByDesc('round')
            ->first();

        if ($lineup) {
            $players = $lineup->players()
                ->where('competition_lineup_players.is_starter', true)
                ->get()
                ->map(fn($p) => [
                    'id'       => $p->id,
                    'name'     => $p->name,
                    'position' => $p->pivot->role,
                    'power'    => round($p->strength * ($p->fitness / 100) * (float) $p->form_factor, 2),
                ]);

            return ['players' => $players, 'formation' => $lineup->formation];
        }

        return ['players' => $this->autoSelectPlayers($leagueTeam), 'formation' => '4-4-2'];
    }

    protected function autoSelectPlayers(LeagueTeam $leagueTeam): Collection
    {
        $all = $leagueTeam->players()
            ->where('status', 'active')
            ->get()
            ->map(fn($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'position' => $p->position,
                'power'    => round($p->strength * ($p->fitness / 100) * (float) $p->form_factor, 2),
            ]);

        $pick = fn(string $pos, int $n) => $all
            ->where('position', $pos)
            ->sortByDesc('power')
            ->take($n)
            ->values();

        return collect()
            ->merge($pick('goalkeeper', 1))
            ->merge($pick('defender',   4))
            ->merge($pick('midfielder', 4))
            ->merge($pick('forward',    2));
    }

    // ── Formação ─────────────────────────────────────────────────────

    private function formationModifier(string $formation, int $sector): float
    {
        $parts = array_map('intval', explode('-', $formation));
        $def   = $parts[0];
        $fwd   = end($parts);
        $mid   = array_sum($parts) - $def - $fwd;

        $base = 0.70;
        $flex = 0.30;
        $sector = max(1, min(5, $sector));

        return match ($sector) {
            1 => $base + $flex * ($def / 4.0),
            2 => $base + $flex * (($def / 4.0) * 0.60 + ($mid / 4.0) * 0.40),
            3 => $base + $flex * ($mid / 4.0),
            4 => $base + $flex * (($fwd / 2.0) * 0.60 + ($mid / 4.0) * 0.40),
            5 => $base + $flex * ($fwd / 2.0),
        };
    }

    // ── Decisão tática ───────────────────────────────────────────────

    private function shouldAdvance(int $scoreDiff, int $playsLeft, int $sector, string $possession): bool
    {
        $prob = 0.60;

        $prob += match (true) {
            $scoreDiff >= 2   => -0.20,
            $scoreDiff === 1  => -0.10,
            $scoreDiff === 0  =>  0.00,
            $scoreDiff === -1 =>  0.15,
            default           =>  0.25,
        };

        if ($playsLeft <= 15) {
            $prob += match (true) {
                $scoreDiff < 0 =>  0.15,
                $scoreDiff > 0 => -0.10,
                default        =>  0.05,
            };
        }

        $ownHalf = $possession === 'home' ? $sector <= 2 : $sector >= 4;
        if ($ownHalf) $prob += 0.10;

        return (random_int(1, 1000) / 1000) <= max(0.15, min(0.90, $prob));
    }

    // ── Disputa de setor ─────────────────────────────────────────────

    private function resolveSector(
        Collection $home, Collection $away,
        int $sector, string $possession,
        string $homeFormation, string $awayFormation,
    ): array {
        $awaySector = 6 - $sector;

        $homePow = $this->sectorPower($home, $sector)     * $this->formationModifier($homeFormation, $sector);
        $awayPow = $this->sectorPower($away, $awaySector) * $this->formationModifier($awayFormation, $awaySector);

        $range   = max($homePow, $awayPow) * self::LUCK_SECTOR;
        $homePow = max(1, $homePow + $this->randomNoise($range));
        $awayPow = max(1, $awayPow + $this->randomNoise($range));

        $homeProb = $homePow / ($homePow + $awayPow);
        $winner   = (random_int(1, 1000) / 1000) <= $homeProb ? 'home' : 'away';

        return ['possession_change' => $winner !== $possession];
    }

    // ── Finalização ──────────────────────────────────────────────────

    private function resolveShot(Collection $home, Collection $away, string $attackingTeam): array
    {
        $attackers = $attackingTeam === 'home' ? $home : $away;
        $defenders = $attackingTeam === 'home' ? $away : $home;

        $scorerPool = $attackers->map(fn($p) => array_merge($p, [
            'scorer_weight' => match ($p['position']) {
                'forward'    => $p['power'] * 3.0,
                'midfielder' => $p['power'] * 1.5,
                'defender'   => $p['power'] * 0.5,
                'goalkeeper' => 0.05,
            },
        ]));

        $shooter    = $attackers->where('position', 'forward')->sortByDesc('power')->first()
            ?? $attackers->sortByDesc('power')->first();
        $goalkeeper = $defenders->where('position', 'goalkeeper')->first()
            ?? $defenders->sortByDesc('power')->first();

        if (! $shooter || ! $goalkeeper) {
            return ['on_target' => false, 'goal' => false, 'scorer_id' => null, 'scorer_name' => null];
        }

        $onTarget = (random_int(1, 1000) / 1000) <= min(0.90, 0.55 + ($shooter['power'] / 200));
        if (! $onTarget) {
            return ['on_target' => false, 'goal' => false, 'scorer_id' => null, 'scorer_name' => null];
        }

        $shotPow  = $shooter['power']    + $this->randomNoise($shooter['power']    * self::LUCK_SHOT);
        $gkPow    = $goalkeeper['power'] + $this->randomNoise($goalkeeper['power'] * self::LUCK_SHOT);
        $goal     = (random_int(1, 1000) / 1000) <= ($shotPow / max(1, $shotPow + $gkPow));

        $scorer = null;
        if ($goal && $scorerPool->isNotEmpty()) {
            $totalWeight = $scorerPool->sum('scorer_weight');
            $rand        = (random_int(1, 10000) / 10000) * $totalWeight;
            $cumulative  = 0;
            foreach ($scorerPool as $candidate) {
                $cumulative += $candidate['scorer_weight'];
                if ($rand <= $cumulative) { $scorer = $candidate; break; }
            }
            $scorer ??= $scorerPool->last();
        }

        return [
            'on_target'   => true,
            'goal'        => $goal,
            'scorer_id'   => $scorer['id']   ?? null,
            'scorer_name' => $scorer['name'] ?? null,
        ];
    }

    // ── Narração ─────────────────────────────────────────────────────

    private function buildNarratorContext(
        string $possession, string $homeTeamName, string $awayTeamName,
        int $play, int $homeScore, int $awayScore,
    ): array {
        return [
            'team'       => $possession === 'home' ? $homeTeamName : $awayTeamName,
            'opponent'   => $possession === 'home' ? $awayTeamName : $homeTeamName,
            'minute'     => $play,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ];
    }

    private function narrateKeyMoment(string $situation, string $team, int $play, array $context): array
    {
        return [
            'type'      => 'narration',
            'team'      => $team,
            'play'      => $play,
            'situation' => $situation,
            'narration' => $this->narrator->narrate($situation, $context),
        ];
    }

    private function advanceSituation(int $sector, string $possession, int $playsLeft, int $scoreDiff): ?string
    {
        $isAttackSector = $possession === 'home' ? $sector >= 4 : $sector <= 2;
        $isMidfield     = $sector === 3;

        if ($isAttackSector) {
            if ($playsLeft <= 15 && $scoreDiff < 0) return 'late_pressure';
            if ($playsLeft <= 15 && $scoreDiff > 0) return 'holding_lead';
            return 'attack_approach';
        }

        return $isMidfield ? 'midfield_battle' : null;
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function sectorPower(Collection $players, int $sector): float
    {
        $weights = self::SECTOR_WEIGHTS[max(1, min(5, $sector))];
        return $players->sum(fn($p) => $p['power'] * ($weights[$p['position']] ?? 0.30));
    }

    private function randomNoise(float $range): float
    {
        if ($range <= 0) return 0.0;
        return random_int((int) (-$range * 100), (int) ($range * 100)) / 100;
    }
}
