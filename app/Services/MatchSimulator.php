<?php

namespace App\Services;

use App\Models\LeagueLineup;
use App\Models\LeagueMatch;
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
     *   home_formation: string,
     *   away_formation: string,
     *   events: list<array{type:string, team:string, play:int}>
     * }
     */
    public function simulate(LeagueMatch $match): array
    {
        $homeData = $this->loadLineup($match->homeTeam, $match->round ?? 0);
        $awayData = $this->loadLineup($match->awayTeam, $match->round ?? 0);

        $home            = $homeData['players'];
        $away            = $awayData['players'];
        $homeFormation   = $homeData['formation'];
        $awayFormation   = $awayData['formation'];

        // Estado inicial
        $sector     = 3;                                   // começa no meio-campo
        $possession = random_int(0, 1) ? 'home' : 'away'; // sorteio de posse

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

            // ── Decisão tática: avançar ou manter setor? ────────────
            $advance = $this->shouldAdvance($scoreDiff, $playsLeft, $sector, $possession);

            // Calcula o setor alvo (sempre ±1 — progressão obrigatória)
            $targetSector = $advance
                ? ($possession === 'home' ? $sector + 1 : $sector - 1)
                : $sector;

            // ── Chegou à área: chute! ────────────────────────────────
            // A bola nunca "pula" setores — chegar aqui exige ter
            // disputado todos os setores intermediários anteriores.
            if ($advance && ($targetSector > 5 || $targetSector < 1)) {
                $shot = $this->resolveShot($home, $away, $possession);

                if ($possession === 'home') {
                    $homeShots++;
                    if ($shot['on_target']) {
                        $homeShotsOnTarget++;
                    }
                } else {
                    $awayShots++;
                    if ($shot['on_target']) {
                        $awayShotsOnTarget++;
                    }
                }

                if ($shot['goal']) {
                    if ($possession === 'home') {
                        $homeScore++;
                    } else {
                        $awayScore++;
                    }

                    $events[] = ['type' => 'goal', 'team' => $possession, 'play' => $play];

                    // Reinicia no centro: quem sofreu o gol saca
                    $sector     = 3;
                    $possession = $possession === 'home' ? 'away' : 'home';
                } else {
                    // Goleiro segura → time defensor constrói do fundo
                    // Novo possuidora precisa percorrer todos os setores
                    $sector     = $possession === 'home' ? 1 : 5;
                    $possession = $possession === 'home' ? 'away' : 'home';
                }

                continue;
            }

            // ── Disputa de posse no setor alvo ──────────────────────
            $targetSector = max(1, min(5, $targetSector));
            $contest      = $this->resolveSector(
                $home, $away,
                $targetSector, $possession,
                $homeFormation, $awayFormation
            );

            $sector = $targetSector;

            if ($contest['possession_change']) {
                $possession = $possession === 'home' ? 'away' : 'home';
                // Nota: o setor permanece o mesmo após perda de bola.
                // O novo time precisa avançar setor a setor até chegar ao gol,
                // passando obrigatoriamente pelo meio-campo (setor 3).
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
                'home_formation'       => $result['home_formation'],
                'away_formation'       => $result['away_formation'],
                'events'               => $result['events'],
            ],
        ]);

        return $match->fresh();
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
    private function loadLineup(LeagueTeam $team, int $round): array
    {
        $lineup = $team->lineups()
            ->where('status', 'active')
            ->whereIn('round', [$round, 0])
            ->orderByDesc('round')   // override de rodada tem prioridade
            ->first();

        if ($lineup) {
            $players = $lineup->players()
                ->where('league_lineup_players.is_starter', true)
                ->get()
                ->map(fn($p) => [
                    'position' => $p->pivot->role,
                    'power'    => round(
                        $p->strength * ($p->fitness / 100) * (float) $p->form_factor,
                        2
                    ),
                ]);

            return ['players' => $players, 'formation' => $lineup->formation];
        }

        // Fallback: auto-seleção por força em 4-4-2
        return [
            'players'   => $this->autoSelectPlayers($team),
            'formation' => '4-4-2',
        ];
    }

    /**
     * Seleciona automaticamente os 11 melhores jogadores em 4-4-2.
     * Usado como fallback quando o manager não escalou o time.
     */
    private function autoSelectPlayers(LeagueTeam $team): Collection
    {
        $all = $team->players()
            ->where('status', 'active')
            ->get()
            ->map(fn($p) => [
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
     * Calcula o multiplicador de poder de um time em um setor,
     * dado o esquema tático (formação).
     *
     * Baseline: 4-4-2  →  todos os setores = 1.00
     *
     * Impacto de formações alternativas:
     *   4-3-3 → setores 4-5 +15 %, setor 3 -7 %
     *   5-3-2 → setores 1-2 +7 %,  setor 5 estável
     *   3-5-2 → setor  3   +7 %,  setores 1-2 -7 %
     *
     * Fórmula:
     *   base = 0.70 (independente da formação)
     *   flex = 0.30 × escalaGrupo
     *
     * Cada setor é influenciado principalmente pelo grupo posicional
     * mais relevante naquele setor:
     *   Setor 1-2 → defensores
     *   Setor 3   → meios-campistas
     *   Setor 4-5 → atacantes
     */
    private function formationModifier(string $formation, int $sector): float
    {
        $parts = array_map('intval', explode('-', $formation));
        $def   = $parts[0];
        $fwd   = end($parts);
        $mid   = array_sum($parts) - $def - $fwd;

        // Escala em relação à baseline 4-4-2
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
     * Define se o time com a posse deve avançar para o próximo setor
     * ou manter e consolidar a bola onde está.
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
            $scoreDiff >= 2   => -0.20,  // vencendo fácil → segura
            $scoreDiff === 1  => -0.10,  // vencendo por 1 → cauteloso
            $scoreDiff === 0  =>  0.00,  // empate → neutro
            $scoreDiff === -1 =>  0.15,  // perdendo → avança
            default           =>  0.25,  // perdendo por 2+ → desesperado
        };

        // Urgência nos últimos 15 jogadas
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
     * Calcula quem vence a disputa de posse em um setor,
     * levando em conta a formação de ambos os times.
     *
     * Poder efetivo por time:
     *   Σ (força_jogador × peso_posição_no_setor) × modificador_formação × ruído_sorte
     *
     * Home usa peso[sector], Away usa peso[6 - sector] (espelho do campo).
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

        $homePow = $this->sectorPower($home, $sector)      * $this->formationModifier($homeFormation, $sector);
        $awayPow = $this->sectorPower($away, $awaySector)  * $this->formationModifier($awayFormation, $awaySector);

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
     * Duas etapas:
     *   1. Chute no alvo? (55 % base + bônus pela força do finalizador)
     *   2. Gol? (força do finalizador vs força do goleiro com ±20 % ruído)
     *
     * Finalizador = melhor atacante (fallback: jogador mais forte).
     * Goleiro      = goalkeeper do time defensor (fallback: mais forte).
     */
    private function resolveShot(
        Collection $home,
        Collection $away,
        string     $attackingTeam,
    ): array {
        $attackers = $attackingTeam === 'home' ? $home : $away;
        $defenders = $attackingTeam === 'home' ? $away : $home;

        $shooter    = $attackers->where('position', 'forward')->sortByDesc('power')->first()
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
        $shotPow  = $shooter['power']    + $this->randomNoise($shooter['power']    * self::LUCK_SHOT);
        $gkPow    = $goalkeeper['power'] + $this->randomNoise($goalkeeper['power'] * self::LUCK_SHOT);

        $total    = max(1, $shotPow + $gkPow);
        $goalProb = $shotPow / $total;
        $goal     = (random_int(1, 1000) / 1000) <= $goalProb;

        return ['on_target' => true, 'goal' => $goal];
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Soma o poder efetivo dos jogadores de um time em um setor,
     * ponderado pelos pesos de posição daquele setor.
     */
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
