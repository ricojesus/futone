<?php

namespace App\Services;

use App\Models\CompetitionLineup;
use App\Models\CompetitionMatch;
use App\Models\CompetitionTeam;
use App\Models\LeagueTeam;
use Illuminate\Support\Collection;

class MatchSimulator
{
    // ── Constantes de simulação ──────────────────────────────────────

    private const PLAYS       = 90;
    private const LUCK_SECTOR = 0.15;   // ±15 % de ruído na disputa de setor
    private const LUCK_SHOT   = 0.20;   // ±20 % de ruído na finalização

    /**
     * Pesos de contribuição por posição em cada setor.
     * Índice = setor do ponto de vista do time (1 = área própria, 5 = área adversária).
     * O time adversário usa o espelho: peso[6 - setor].
     */
    private const SECTOR_WEIGHTS = [
        1 => ['goalkeeper' => 1.50, 'defender' => 1.00, 'midfielder' => 0.20, 'forward' => 0.00],
        2 => ['goalkeeper' => 0.00, 'defender' => 1.00, 'midfielder' => 0.60, 'forward' => 0.10],
        3 => ['goalkeeper' => 0.00, 'defender' => 0.30, 'midfielder' => 1.00, 'forward' => 0.30],
        4 => ['goalkeeper' => 0.00, 'defender' => 0.10, 'midfielder' => 0.60, 'forward' => 1.00],
        5 => ['goalkeeper' => 0.00, 'defender' => 0.00, 'midfielder' => 0.20, 'forward' => 1.50],
    ];

    public function __construct(
        private readonly MatchNarrator $narrator = new MatchNarrator(),
    ) {}

    // ── API pública ──────────────────────────────────────────────────

    /**
     * Simula uma partida completa e retorna o resultado.
     *
     * O array de eventos contém entradas de dois tipos:
     *
     *   Tipo 'goal' | 'shot_saved' | 'shot_missed':
     *     { type, team, play, narration }
     *
     *   Tipo 'narration' (momentos-chave sem gol):
     *     { type: 'narration', team, play, situation, narration }
     *
     * @return array{
     *   home_score: int,
     *   away_score: int,
     *   home_possession: int,
     *   away_possession: int,
     *   home_shots: int,
     *   away_shots: int,
     *   home_shots_on_target: int,
     *   away_shots_on_target: int,
     *   home_formation: string,
     *   away_formation: string,
     *   events: list<array>
     * }
     */
    public function simulate(CompetitionMatch $match): array
    {
        $homeData = $this->loadLineup($match->homeTeam, $match->round ?? 0);
        $awayData = $this->loadLineup($match->awayTeam, $match->round ?? 0);

        $home          = $homeData['players'];
        $away          = $awayData['players'];
        $homeFormation = $homeData['formation'];
        $awayFormation = $awayData['formation'];

        // Nomes dos times para narração
        $homeTeamName = $match->homeTeam->name;
        $awayTeamName = $match->awayTeam->name;

        // Estado inicial
        $sector     = 3;
        $possession = random_int(0, 1) ? 'home' : 'away';

        $homeScore = 0;
        $awayScore = 0;

        // Contadores de estatísticas
        $homePossCount     = 0;
        $homeShots         = 0;
        $awayShots         = 0;
        $homeShotsOnTarget = 0;
        $awayShotsOnTarget = 0;
        $events            = [];

        for ($play = 1; $play <= self::PLAYS; $play++) {
            if ($possession === 'home') {
                $homePossCount++;
            }

            $playsLeft = self::PLAYS - $play;
            $scoreDiff = $possession === 'home'
                ? $homeScore - $awayScore
                : $awayScore - $homeScore;

            // Contexto de narração (atualizado a cada jogada)
            $narratorCtx = $this->buildNarratorContext(
                $possession, $homeTeamName, $awayTeamName, $play, $homeScore, $awayScore
            );

            // ── Decisão tática: avançar ou manter setor? ────────────
            $advance      = $this->shouldAdvance($scoreDiff, $playsLeft, $sector, $possession);
            $targetSector = $advance
                ? ($possession === 'home' ? $sector + 1 : $sector - 1)
                : $sector;

            // ── Chegou à área: chute! ────────────────────────────────
            if ($advance && ($targetSector > 5 || $targetSector < 1)) {
                $shot = $this->resolveShot($home, $away, $possession);

                if ($possession === 'home') {
                    $homeShots++;
                    if ($shot['on_target']) $homeShotsOnTarget++;
                } else {
                    $awayShots++;
                    if ($shot['on_target']) $awayShotsOnTarget++;
                }

                if ($shot['goal']) {
                    // Atualiza placar ANTES de narrar para mostrar o novo resultado
                    if ($possession === 'home') $homeScore++;
                    else $awayScore++;

                    $events[] = [
                        'type'         => 'goal',
                        'team'         => $possession,
                        'play'         => $play,
                        'scorer_id'    => $shot['scorer_id'],
                        'scorer_name'  => $shot['scorer_name'],
                        'narration'    => $this->narrator->narrate('goal', array_merge(
                            $narratorCtx,
                            [
                                'home_score'  => $homeScore,
                                'away_score'  => $awayScore,
                                'scorer_name' => $shot['scorer_name'],
                            ]
                        )),
                    ];

                    $sector     = 3;
                    $possession = $possession === 'home' ? 'away' : 'home';

                } elseif ($shot['on_target']) {
                    // Chute no alvo mas defendido
                    $events[] = [
                        'type'      => 'shot_saved',
                        'team'      => $possession,
                        'play'      => $play,
                        'narration' => $this->narrator->narrate('shot_saved', $narratorCtx),
                    ];

                    $sector     = $possession === 'home' ? 1 : 5;
                    $possession = $possession === 'home' ? 'away' : 'home';

                } else {
                    // Chute para fora
                    $events[] = [
                        'type'      => 'shot_missed',
                        'team'      => $possession,
                        'play'      => $play,
                        'narration' => $this->narrator->narrate('shot_missed', $narratorCtx),
                    ];

                    $sector     = $possession === 'home' ? 1 : 5;
                    $possession = $possession === 'home' ? 'away' : 'home';
                }

                continue;
            }

            // ── Disputa de posse no setor alvo ──────────────────────
            $targetSector = max(1, min(5, $targetSector));
            $contest      = $this->resolveSector(
                $home, $away, $targetSector, $possession,
                $homeFormation, $awayFormation
            );

            $sector = $targetSector;

            if ($contest['possession_change']) {
                $loser      = $possession;
                $possession = $possession === 'home' ? 'away' : 'home';

                // Perda perigosa de bola no ataque?
                if ($this->narrator->isCounterAttack($sector, $possession)) {
                    // Contra-ataque detectado
                    $events[] = $this->narrateKeyMoment(
                        'counter_attack', $possession, $play,
                        $this->buildNarratorContext(
                            $possession, $homeTeamName, $awayTeamName, $play, $homeScore, $awayScore
                        )
                    );
                } elseif ($this->narrator->shouldNarrateLoss($sector, $loser)) {
                    // Perda perigosa sem contra-ataque imediato
                    $events[] = $this->narrateKeyMoment(
                        'possession_lost_attack', $loser, $play, $narratorCtx
                    );
                }
            } else {
                // Posse mantida — narrar avanço ao ataque ou meio-campo
                if ($advance) {
                    if ($this->narrator->shouldNarrateAdvance($targetSector, $possession)) {
                        $situation = $this->advanceSituation($targetSector, $possession, $playsLeft, $scoreDiff);
                        if ($situation) {
                            $events[] = $this->narrateKeyMoment($situation, $possession, $play, $narratorCtx);
                        }
                    }
                }
            }
        }

        return [
            'home_score'           => $homeScore,
            'away_score'           => $awayScore,
            'home_possession'      => (int) round($homePossCount / self::PLAYS * 100),
            'away_possession'      => 100 - (int) round($homePossCount / self::PLAYS * 100),
            'home_shots'           => $homeShots,
            'away_shots'           => $awayShots,
            'home_shots_on_target' => $homeShotsOnTarget,
            'away_shots_on_target' => $awayShotsOnTarget,
            'home_formation'       => $homeFormation,
            'away_formation'       => $awayFormation,
            'events'               => $events,
        ];
    }

    /**
     * Simula e persiste o resultado direto no LeagueMatch.
     */
    public function simulateAndSave(CompetitionMatch $match): CompetitionMatch
    {
        $result = $this->simulate($match);

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

        /** @var CompetitionMatch $fresh */
        $fresh = $match->fresh();
        return $fresh;
    }

    // ── Carregamento de escalação ────────────────────────────────────

    /**
     * Carrega a escalação ativa do time para a rodada informada.
     *
     * Prioridade:
     *   1. Override específico da rodada (round = N)
     *   2. Escalação padrão (round = 0)
     *   3. Fallback automático: melhores jogadores em 4-4-2
     *
     * @return array{players: Collection, formation: string}
     */
    private function loadLineup(CompetitionTeam $compTeam, int $round): array
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
                    'power'    => round(
                        $p->strength * ($p->fitness / 100) * (float) $p->form_factor,
                        2
                    ),
                ]);

            return ['players' => $players, 'formation' => $lineup->formation];
        }

        return [
            'players'   => $this->autoSelectPlayers($leagueTeam),
            'formation' => '4-4-2',
        ];
    }

    /**
     * Seleciona automaticamente os 11 melhores jogadores em 4-4-2.
     */
    private function autoSelectPlayers(LeagueTeam $leagueTeam): Collection
    {
        $all = $leagueTeam->players()
            ->where('status', 'active')
            ->get()
            ->map(fn($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'position' => $p->position,
                'power'    => round(
                    $p->strength * ($p->fitness / 100) * (float) $p->form_factor,
                    2
                ),
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

    // ── Modificador de formação ──────────────────────────────────────

    /**
     * Multiplicador de poder do time em um setor baseado na formação.
     *
     * Baseline: 4-4-2 → 1.00 em todos os setores.
     *   4-3-3 → setores 4-5 +15 %, setor 3 -8 %
     *   5-3-2 → setores 1-2 +7 %,  setor 3 -8 %
     *   3-5-2 → setor  3   +7 %,  setores 1-2 -8 %
     */
    private function formationModifier(string $formation, int $sector): float
    {
        $parts = array_map('intval', explode('-', $formation));
        $def   = $parts[0];
        $fwd   = end($parts);
        $mid   = array_sum($parts) - $def - $fwd;

        $defScale = $def / 4.0;
        $midScale = $mid / 4.0;
        $fwdScale = $fwd / 2.0;

        $base = 0.70;
        $flex = 0.30;

        $sector = max(1, min(5, $sector));

        return match ($sector) {
            1 => $base + $flex * $defScale,
            2 => $base + $flex * ($defScale * 0.60 + $midScale * 0.40),
            3 => $base + $flex * $midScale,
            4 => $base + $flex * ($fwdScale * 0.60 + $midScale * 0.40),
            5 => $base + $flex * $fwdScale,
        };
    }

    // ── Decisão tática ───────────────────────────────────────────────

    /**
     * Define se o time com a posse deve avançar para o próximo setor.
     *
     * Probabilidade base: 60%
     *   + ajuste por placar / urgência / posição no campo
     */
    private function shouldAdvance(
        int    $scoreDiff,
        int    $playsLeft,
        int    $sector,
        string $possession,
    ): bool {
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
        if ($ownHalf) {
            $prob += 0.10;
        }

        $prob = max(0.15, min(0.90, $prob));

        return (random_int(1, 1000) / 1000) <= $prob;
    }

    // ── Disputa de setor ─────────────────────────────────────────────

    /**
     * Calcula quem vence a disputa de posse em um setor,
     * levando em conta a formação de ambos os times.
     */
    private function resolveSector(
        Collection $home,
        Collection $away,
        int        $sector,
        string     $possession,
        string     $homeFormation,
        string     $awayFormation,
    ): array {
        $awaySector = 6 - $sector;

        $homePow = $this->sectorPower($home, $sector)     * $this->formationModifier($homeFormation, $sector);
        $awayPow = $this->sectorPower($away, $awaySector) * $this->formationModifier($awayFormation, $awaySector);

        $range   = max($homePow, $awayPow) * self::LUCK_SECTOR;
        $homePow = max(1, $homePow + $this->randomNoise($range));
        $awayPow = max(1, $awayPow + $this->randomNoise($range));

        $homeProb = $homePow / ($homePow + $awayPow);
        $homeWins = (random_int(1, 1000) / 1000) <= $homeProb;
        $winner   = $homeWins ? 'home' : 'away';

        return ['possession_change' => $winner !== $possession];
    }

    // ── Finalização ──────────────────────────────────────────────────

    /**
     * Resolve um chute a gol em duas etapas:
     *   1. Chute no alvo? (55 % base + bônus pela força do finalizador)
     *   2. Gol? (força do finalizador vs força do goleiro com ±20 % ruído)
     */
    private function resolveShot(
        Collection $home,
        Collection $away,
        string     $attackingTeam,
    ): array {
        $attackers  = $attackingTeam === 'home' ? $home : $away;
        $defenders  = $attackingTeam === 'home' ? $away : $home;

        // Goleador potencial: atacantes têm 3× mais chance que meias, meias 2× que defensores
        $scorerPool = $attackers->map(fn($p) => array_merge($p, [
            'scorer_weight' => match ($p['position']) {
                'forward'    => $p['power'] * 3.0,
                'midfielder' => $p['power'] * 1.5,
                'defender'   => $p['power'] * 0.5,
                default      => 0.0, // goleiro não marca
            },
        ]))->filter(fn($p) => $p['scorer_weight'] > 0);

        // Selecionar o finalizador pelo maior power (para calcular probabilidade de gol)
        $shooter = $attackers->where('position', 'forward')->sortByDesc('power')->first()
            ?? $attackers->sortByDesc('power')->first();

        $goalkeeper = $defenders->where('position', 'goalkeeper')->first()
            ?? $defenders->sortByDesc('power')->first();

        if (! $shooter || ! $goalkeeper) {
            return ['on_target' => false, 'goal' => false, 'scorer_id' => null, 'scorer_name' => null];
        }

        $onTargetProb = min(0.90, 0.55 + ($shooter['power'] / 200));
        $onTarget     = (random_int(1, 1000) / 1000) <= $onTargetProb;

        if (! $onTarget) {
            return ['on_target' => false, 'goal' => false, 'scorer_id' => null, 'scorer_name' => null];
        }

        $shotPow  = $shooter['power']    + $this->randomNoise($shooter['power']    * self::LUCK_SHOT);
        $gkPow    = $goalkeeper['power'] + $this->randomNoise($goalkeeper['power'] * self::LUCK_SHOT);

        $total    = max(1, $shotPow + $gkPow);
        $goalProb = $shotPow / $total;
        $goal     = (random_int(1, 1000) / 1000) <= $goalProb;

        // Sortear goleador ponderado pelo scorer_weight
        $scorer = null;
        if ($goal && $scorerPool->isNotEmpty()) {
            $totalWeight = $scorerPool->sum('scorer_weight');
            $rand        = (random_int(1, 10000) / 10000) * $totalWeight;
            $cumulative  = 0;
            foreach ($scorerPool as $candidate) {
                $cumulative += $candidate['scorer_weight'];
                if ($rand <= $cumulative) {
                    $scorer = $candidate;
                    break;
                }
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

    // ── Helpers de narração ──────────────────────────────────────────

    /**
     * Monta o contexto de narração para o narrador a partir do estado atual.
     */
    private function buildNarratorContext(
        string $possession,
        string $homeTeamName,
        string $awayTeamName,
        int    $play,
        int    $homeScore,
        int    $awayScore,
    ): array {
        return [
            'team'       => $possession === 'home' ? $homeTeamName : $awayTeamName,
            'opponent'   => $possession === 'home' ? $awayTeamName : $homeTeamName,
            'minute'     => $play,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ];
    }

    /**
     * Monta a entrada de narração para o array de eventos.
     */
    private function narrateKeyMoment(
        string $situation,
        string $team,
        int    $play,
        array  $context,
    ): array {
        return [
            'type'      => 'narration',
            'team'      => $team,
            'play'      => $play,
            'situation' => $situation,
            'narration' => $this->narrator->narrate($situation, $context),
        ];
    }

    /**
     * Determina qual situação de narração corresponde ao avanço de setor.
     * Leva em conta fase final e contexto do placar.
     */
    private function advanceSituation(
        int    $sector,
        string $possession,
        int    $playsLeft,
        int    $scoreDiff,
    ): ?string {
        $isAttackSector = $possession === 'home' ? $sector >= 4 : $sector <= 2;
        $isMidfield     = $sector === 3;

        if ($isAttackSector) {
            // Últimos 15 minutos com placar diferente → narração contextual
            if ($playsLeft <= 15 && $scoreDiff < 0) {
                return 'late_pressure';
            }

            if ($playsLeft <= 15 && $scoreDiff > 0) {
                return 'holding_lead';
            }

            return 'attack_approach';
        }

        if ($isMidfield) {
            return 'midfield_battle';
        }

        return null;
    }

    // ── Helpers de simulação ─────────────────────────────────────────

    private function sectorPower(Collection $players, int $sector): float
    {
        $sector  = max(1, min(5, $sector));
        $weights = self::SECTOR_WEIGHTS[$sector];

        return $players->sum(
            fn($p) => $p['power'] * ($weights[$p['position']] ?? 0.30)
        );
    }

    private function randomNoise(float $range): float
    {
        if ($range <= 0) {
            return 0.0;
        }

        return random_int((int) (-$range * 100), (int) ($range * 100)) / 100;
    }
}
