<?php

namespace App\Services;

class MatchEngine
{
    private array $events = [];

    private int $homeGoals = 0;
    private int $awayGoals = 0;

    private string $possession;
    private string $homeTeamName;
    private string $awayTeamName;

    private array $teamStats = [];
    private array $playerStats = [];
    private int $totalPossessionTicks = 0;

    public function play(array $home, array $away): array
    {
        $this->homeTeamName = $home['name'];
        $this->awayTeamName = $away['name'];

        $this->initStats($home, $away);

        $ballSector = 'meio';
        $minute = 1;

        $this->possession = rand(0, 1)
            ? $this->homeTeamName
            : $this->awayTeamName;

        while ($minute <= 90) {

            // Nem todo minuto gera evento
            if (rand(1, 100) > 25) {
                $minute++;
                continue;
            }

            $winner = $this->dispute($home, $away, $ballSector);
            $winnerName = $winner['name'];
            $hadPossession = $this->possession === $winnerName;

            $player = $this->randomPlayer($winner, $ballSector);

            // 📊 Posse
            $this->teamStats[$winnerName]['possession']++;
            $this->totalPossessionTicks++;

            // 🔄 Troca ou manutenção da posse
            if (!$hadPossession) {
                $this->events[] =
                    "Minuto {$minute}: {$player['name']} recupera a bola para o {$winnerName}";
                $this->playerStats[$player['name']]['recoveries']++;
                $this->possession = $winnerName;
                $ballSector = 'defesa';
            } else {
                $this->events[] =
                    "Minuto {$minute}: {$player['name']} mantém a posse para o {$winnerName}";
                $this->playerStats[$player['name']]['passes']++;
            }

            // 🔁 Transições
            if ($ballSector === 'defesa') {
                $ballSector = 'meio';
                $this->events[] =
                    "{$player['name']} sai jogando da defesa para o {$winnerName}";

            } elseif ($ballSector === 'meio') {
                $ballSector = 'ataque';
                $this->events[] =
                    "{$player['name']} avança para o ataque do {$winnerName}";

            } elseif ($ballSector === 'ataque') {

                // 🎯 Tentativa de gol
                $this->events[] =
                    "{$player['name']} finaliza para o gol do {$winnerName}";

                $this->teamStats[$winnerName]['shots']++;
                $this->playerStats[$player['name']]['shots']++;

                // Chance dinâmica de gol baseada no ataque
                $attackForce = $this->sectorForce($winner, 'ataque');
                $goalChance = min(45, 15 + intdiv($attackForce, 20));

                if (rand(1, 100) <= $goalChance) {
                    $this->registerGoal($winnerName, $player['name'], $minute);
                } else {
                    $defender = $winnerName === $this->homeTeamName ? $away : $home;
                    $goalkeeper = $this->goalkeeper($defender);

                    $this->teamStats[$winnerName]['shots_on_target']++;

                    $this->events[] =
                        "{$goalkeeper['name']} faz a defesa para o {$defender['name']}";
                }

                // Reinicia jogada
                $ballSector = 'defesa';
                $this->possession =
                    $winnerName === $this->homeTeamName
                        ? $this->awayTeamName
                        : $this->homeTeamName;
            }

            $minute++;
        }

        return $this->result($home, $away);
    }

    // =========================
    // 📊 ESTATÍSTICAS
    // =========================

    private function initStats(array $home, array $away): void
    {
        foreach ([$home['name'], $away['name']] as $team) {
            $this->teamStats[$team] = [
                'possession' => 0,
                'shots' => 0,
                'shots_on_target' => 0,
                'goals' => 0,
            ];
        }
    
        foreach ($home['players'] as $player) {
            $this->playerStats[$player['name']] = [
                'team' => $home['name'],
                'goals' => 0,
                'shots' => 0,
                'passes' => 0,
                'recoveries' => 0,
            ];
        }
    
        foreach ($away['players'] as $player) {
            $this->playerStats[$player['name']] = [
                'team' => $away['name'],
                'goals' => 0,
                'shots' => 0,
                'passes' => 0,
                'recoveries' => 0,
            ];
        }
    }
    

    // =========================
    // ⚽ REGRAS DO JOGO
    // =========================

    private function dispute(array $home, array $away, string $sector): array
    {
        $homeForce = $this->sectorForce($home, $sector) + rand(0, 15);
        $awayForce = $this->sectorForce($away, $sector) + rand(0, 15);

        return $homeForce >= $awayForce ? $home : $away;
    }

    private function sectorForce(array $team, string $sector): int
    {
        return array_sum(
            array_map(
                fn ($p) => $p['position'] === $sector ? $p['strength'] : 0,
                $team['players']
            )
        );
    }

    private function randomPlayer(array $team, string $sector): array
    {
        $players = array_values(
            array_filter(
                $team['players'],
                fn ($p) => $p['position'] === $sector
            )
        );

        return $players[array_rand($players)];
    }

    private function goalkeeper(array $team): array
    {
        foreach ($team['players'] as $player) {
            if ($player['position'] === 'goleiro') {
                return $player;
            }
        }

        return [
            'name' => 'Goleiro',
            'position' => 'goleiro',
            'strength' => 50
        ];
    }

    private function registerGoal(string $teamName, string $playerName, int $minute): void
    {
        if ($teamName === $this->homeTeamName) {
            $this->homeGoals++;
        } else {
            $this->awayGoals++;
        }

        $this->teamStats[$teamName]['goals']++;
        $this->teamStats[$teamName]['shots_on_target']++;
        $this->playerStats[$playerName]['goals']++;

        $this->events[] =
            "⚽ Gol de {$playerName} para o {$teamName} aos {$minute} minutos!";
    }

    // =========================
    // 📦 RESULTADO FINAL
    // =========================

    private function result(array $home, array $away): array
    {
        foreach ($this->teamStats as $team => &$stats) {
            $stats['possession_percent'] = round(
                ($stats['possession'] / max(1, $this->totalPossessionTicks)) * 100
            );
            unset($stats['possession']);
        }

        return [
            'home' => $home['name'],
            'away' => $away['name'],
            'score' => "{$this->homeGoals} x {$this->awayGoals}",
            'home_goals' => $this->homeGoals,
            'away_goals' => $this->awayGoals,
            'events' => $this->events,
            'stats' => $this->teamStats,
            'player_stats' => $this->playerStats,
        ];
    }
}
