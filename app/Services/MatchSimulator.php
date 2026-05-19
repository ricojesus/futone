<?php

namespace App\Services;

use App\Models\LeagueMatch;
use App\Models\LeagueTeam;
use Illuminate\Support\Collection;

class MatchSimulator
{
    // ── Constantes de simulação ──────────────────────────────────────

    private const PLAYS         = 90;
    private const LUCK_SECTOR   = 0.15;   // ±15% de ruído na disputa de setor
    private const LUCK_SHOT     = 0.20;   // ±20% de ruído na finalização

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

    // ── API pública ──────────────────────────────────────────────────

    /**
     * Simula uma partida completa e retorna o resultado.
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
     *   events: list<array{type:string, team:string, play:int}>
     * }
     */
    public function simulate(LeagueMatch $match): array
    {
        $home = $this->loadPlayers($match->homeTeam);
        $away = $this->loadPlayers($match->awayTeam);

        // Estado inicial
        $sector     = 3;                                 // começa no meio-campo
        $possession = random_int(0, 1) ? 'home' : 'away'; // sorteio de posse
        $homeScore  = 0;
        $awayScore  = 0;

        // Contadores de estatísticas
        $homePossCount      = 0;
        $homeShots          = 0;
        $awayShots          = 0;
        $homeShotsOnTarget  = 0;
        $awayShotsOnTarget  = 0;
        $events             = [];

        for ($play = 1; $play <= self::PLAYS; $play++) {
            if ($possession === 'home') {
                $homePossCount++;
            }

            $playsLeft = self::PLAYS - $play;
            $scoreDiff = $possession === 'home'
                ? $homeScore - $awayScore
                : $awayScore - $homeScore;

            // ── Decisão tática: avançar ou manter setor? ────────────
            $advance = $this->shouldAdvance($scoreDiff, $playsLeft, $sector, $possession);

            // Calcula o setor alvo
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
                    if ($possession === 'home') $homeScore++;
                    else $awayScore++;

                    $events[] = ['type' => 'goal', 'team' => $possession, 'play' => $play];

                    // Reinicia no centro: quem sofreu o gol saca
                    $sector     = 3;
                    $possession = $possession === 'home' ? 'away' : 'home';
                } else {
                    // Goleiro segura → time defensor constrói do fundo
                    $sector     = $possession === 'home' ? 1 : 5;
                    $possession = $possession === 'home' ? 'away' : 'home';
                }

                continue;
            }

            // ── Disputa de posse no setor alvo ──────────────────────
            $targetSector = max(1, min(5, $targetSector));
            $contest      = $this->resolveSector($home, $away, $targetSector, $possession);

            $sector = $targetSector;

            if ($contest['possession_change']) {
                $possession = $possession === 'home' ? 'away' : 'home';
            }
        }

        return [
            'home_score'            => $homeScore,
            'away_score'            => $awayScore,
            'home_possession'       => (int) round($homePossCount / self::PLAYS * 100),
            'away_possession'       => 100 - (int) round($homePossCount / self::PLAYS * 100),
            'home_shots'            => $homeShots,
            'away_shots'            => $awayShots,
            'home_shots_on_target'  => $homeShotsOnTarget,
            'away_shots_on_target'  => $awayShotsOnTarget,
            'events'                => $events,
        ];
    }

    /**
     * Simula e persiste o resultado direto no LeagueMatch.
     */
    public function simulateAndSave(LeagueMatch $match): LeagueMatch
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
                'events'               => $result['events'],
            ],
        ]);

        return $match->fresh();
    }

    // ── Decisão tática ───────────────────────────────────────────────

    /**
     * Define se o time com a posse deve avançar para o próximo setor
     * ou manter e consolidar a bola onde está.
     *
     * Fatores:
     *   scoreDiff       — diferença do ponto de vista de quem tem a bola
     *   playsLeft       — urgência temporal
     *   sector          — posição atual no campo
     *   possession      — time com a bola (para ajuste de setor)
     *
     * Probabilidade base de avançar: 60%
     *   + ajuste por placar (perdendo → mais agressivo)
     *   + ajuste por urgência nos últimos 15 minutos
     *   + ajuste por setor (fundo do campo → mais propenso a avançar)
     */
    private function shouldAdvance(
        int    $scoreDiff,
        int    $playsLeft,
        int    $sector,
        string $possession,
    ): bool {
        $prob = 0.60;

        // Ajuste por placar
        $prob += match (true) {
            $scoreDiff >= 2  => -0.20,  // vencendo fácil → segura
            $scoreDiff === 1 => -0.10,  // vencendo por 1 → cauteloso
            $scoreDiff === 0 =>  0.00,  // empate → neutro
            $scoreDiff === -1 => 0.15,  // perdendo → avança
            default          =>  0.25,  // perdendo por 2+ → desesperado
        };

        // Urgência nos últimos 15 minutos/jogadas
        if ($playsLeft <= 15) {
            $prob += match (true) {
                $scoreDiff < 0 =>  0.15,  // perdendo nos acréscimos → tudo ou nada
                $scoreDiff > 0 => -0.10,  // ganhando nos acréscimos → perde tempo
                default        =>  0.05,
            };
        }

        // Setor: time no próprio campo tende a querer sair
        $ownHalf = $possession === 'home' ? $sector <= 2 : $sector >= 4;
        if ($ownHalf) {
            $prob += 0.10;
        }

        $prob = max(0.15, min(0.90, $prob));

        return (random_int(1, 1000) / 1000) <= $prob;
    }

    // ── Disputa de setor ─────────────────────────────────────────────

    /**
     * Calcula quem vence a disputa de posse em um setor.
     *
     * Poder efetivo de cada time no setor:
     *   Σ (força_jogador × peso_posição_no_setor) × ruído_sorte
     *
     * Home usa peso[sector], Away usa peso[6 - sector] (espelho do campo).
     */
    private function resolveSector(
        Collection $home,
        Collection $away,
        int        $sector,
        string     $possession,
    ): array {
        $homePow = $this->sectorPower($home, $sector);
        $awayPow = $this->sectorPower($away, 6 - $sector);

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
     * Resolve um chute a gol.
     *
     * Atacante: o forward com maior força efetiva disponível.
     *           Se não houver forward, usa o jogador mais forte.
     * Goleiro:  o goalkeeper do time defensor.
     *
     * Duas etapas:
     *   1. Chute no alvo? (70% base + vantagem do atacante)
     *   2. Gol? (força atacante vs força GK com ruído)
     */
    private function resolveShot(
        Collection $home,
        Collection $away,
        string     $attackingTeam,
    ): array {
        $attackers = $attackingTeam === 'home' ? $home : $away;
        $defenders = $attackingTeam === 'home' ? $away : $home;

        $shooter = $attackers->where('position', 'forward')->sortByDesc('power')->first()
            ?? $attackers->sortByDesc('power')->first();

        $goalkeeper = $defenders->where('position', 'goalkeeper')->first()
            ?? $defenders->sortByDesc('power')->first();

        if (! $shooter || ! $goalkeeper) {
            return ['on_target' => false, 'goal' => false];
        }

        // Passo 1: chute no alvo
        $onTargetProb = min(0.90, 0.55 + ($shooter['power'] / 200));
        $onTarget     = (random_int(1, 1000) / 1000) <= $onTargetProb;

        if (! $onTarget) {
            return ['on_target' => false, 'goal' => false];
        }

        // Passo 2: gol ou defesa
        $shotPow = $shooter['power'] + $this->randomNoise($shooter['power'] * self::LUCK_SHOT);
        $gkPow   = $goalkeeper['power'] + $this->randomNoise($goalkeeper['power'] * self::LUCK_SHOT);

        $total   = max(1, $shotPow + $gkPow);
        $goalProb = $shotPow / $total;
        $goal     = (random_int(1, 1000) / 1000) <= $goalProb;

        return ['on_target' => true, 'goal' => $goal];
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Carrega os jogadores ativos de um time com sua força efetiva pré-calculada.
     *
     *   effective_power = strength × (fitness / 100) × form_factor
     */
    private function loadPlayers(LeagueTeam $team): Collection
    {
        return $team->players()
            ->where('status', 'active')
            ->get()
            ->map(fn ($p) => [
                'position' => $p->position,
                'power'    => round(
                    $p->strength * ($p->fitness / 100) * (float) $p->form_factor,
                    2
                ),
            ]);
    }

    /**
     * Soma o poder efetivo dos jogadores de um time em um setor,
     * ponderado pelos pesos de posição daquele setor.
     */
    private function sectorPower(Collection $players, int $sector): float
    {
        $sector  = max(1, min(5, $sector));
        $weights = self::SECTOR_WEIGHTS[$sector];

        return $players->sum(
            fn ($p) => $p['power'] * ($weights[$p['position']] ?? 0.30)
        );
    }

    private function randomNoise(float $range): float
    {
        if ($range <= 0) return 0.0;
        return random_int((int) (-$range * 100), (int) ($range * 100)) / 100;
    }
}
