<?php

namespace App\Services;

use App\Models\LeaguePlayer;
use App\Models\LeagueTeam;

class StaminaService
{
    /**
     * Desgaste base por partida (pontos de fitness).
     * Modificado pela idade e pelo atributo stamina.
     */
    private const BASE_DEGRADATION = 20;

    /**
     * Recuperação base por rodada sem jogo (pontos de fitness).
     * Modificado pela idade e pelo atributo stamina.
     */
    private const BASE_RECOVERY = 30;

    /**
     * Fitness mínima ao retornar de lesão.
     * Evita que o jogador volte 100% e seja imediatamente escalado.
     */
    private const FITNESS_ON_RETURN = 40;

    /**
     * Limiares de risco de lesão [fitness_máxima => probabilidade].
     * Avaliados do mais grave ao mais leve.
     */
    private const INJURY_RISK = [
        20 => 0.35,   // fitness < 20 → 35% de chance por jogo
        40 => 0.15,   // fitness < 40 → 15%
        60 => 0.05,   // fitness < 60 →  5%
        // acima de 60 → sem risco
    ];

    // ── API pública ──────────────────────────────────────────────────

    /**
     * Processa todos os jogadores de um time que participaram de uma partida:
     * - Degasta a fitness
     * - Verifica risco de lesão para os de fitness baixa
     *
     * Chamado pelo game engine após cada partida ser processada.
     *
     * @return array{degraded: int, injured: list<string>}  Resumo para log
     */
    public function processMatchDay(LeagueTeam $team, int $round): array
    {
        $summary = ['degraded' => 0, 'injured' => []];

        $team->players()
            ->where('status', 'active')
            ->each(function (LeaguePlayer $player) use ($round, &$summary) {
                $this->degrade($player);
                $summary['degraded']++;

                if ($this->rollInjury($player->fresh(), $round)) {
                    $summary['injured'][] = $player->name;
                }
            });

        return $summary;
    }

    /**
     * Recupera a fitness de jogadores que não participaram desta rodada
     * (lesionados, bancos, jogadores de times sem partida na rodada).
     *
     * Jogadores lesionados cuja rodada de recuperação chegou voltam como 'active'.
     *
     * Chamado pelo game engine ao encerrar cada rodada.
     */
    public function processRestDay(LeagueTeam $team, int $round): void
    {
        // Jogadores ativos que não jogaram → recuperam fitness
        $team->players()
            ->where('status', 'active')
            ->each(fn (LeaguePlayer $p) => $this->recover($p));

        // Jogadores lesionados cujo prazo encerrou → voltam com fitness reduzida
        $team->players()
            ->where('status', 'injured')
            ->where('injured_until', '<=', $round)
            ->each(function (LeaguePlayer $player) {
                $player->update([
                    'status'        => 'active',
                    'injured_until' => null,
                    'fitness'       => self::FITNESS_ON_RETURN,
                ]);
            });
    }

    /**
     * Retorna o risco de lesão atual de um jogador (0.0–0.35).
     * Usado pela UI para alertar o manager antes de escalar.
     */
    public function injuryRisk(LeaguePlayer $player): float
    {
        foreach (self::INJURY_RISK as $threshold => $risk) {
            if ($player->fitness < $threshold) {
                return $risk;
            }
        }
        return 0.0;
    }

    /**
     * Label de alerta para a UI.
     *
     * fitness >= 70 → "Ótima"
     * fitness >= 55 → "Boa"
     * fitness >= 40 → "Razoável"
     * fitness >= 25 → "Baixa ⚠"
     * fitness <  25 → "Crítica ⛔"
     */
    public function fitnessLabel(int $fitness): string
    {
        return match (true) {
            $fitness >= 70 => 'Ótima',
            $fitness >= 55 => 'Boa',
            $fitness >= 40 => 'Razoável',
            $fitness >= 25 => 'Baixa',
            default        => 'Crítica',
        };
    }

    public function fitnessColor(int $fitness): string
    {
        return match (true) {
            $fitness >= 70 => 'emerald',
            $fitness >= 55 => 'sky',
            $fitness >= 40 => 'amber',
            $fitness >= 25 => 'orange',
            default        => 'red',
        };
    }

    // ── Internos ─────────────────────────────────────────────────────

    private function degrade(LeaguePlayer $player): void
    {
        $loss       = $this->degradationAmount($player);
        $newFitness = max(0, $player->fitness - $loss);
        $player->update(['fitness' => $newFitness]);
    }

    private function recover(LeaguePlayer $player): void
    {
        $gain       = $this->recoveryAmount($player);
        $newFitness = min(100, $player->fitness + $gain);
        $player->update(['fitness' => $newFitness]);
    }

    /**
     * Desgaste por partida:
     *   base × fator_idade × fator_stamina
     *
     * Fator de idade (jovens se cansam menos):
     *   < 22 → 0.70
     *   22–28 → 1.00
     *   29–32 → 1.00 + (idade-28)×0.07  → até 1.28
     *   33+   → 1.30 + (idade-33)×0.06  → cresce sem teto
     *
     * Fator de stamina (alta stamina = menos desgaste):
     *   stamina 100 → 0.75 (–25%)
     *   stamina  50 → 1.00
     *   stamina  10 → 1.20 (+20%)
     */
    private function degradationAmount(LeaguePlayer $player): int
    {
        $age    = $player->age;
        $stam   = $player->stamina;

        $ageFactor = match (true) {
            $age < 22 => 0.70,
            $age < 29 => 1.00,
            $age < 33 => 1.00 + ($age - 28) * 0.07,
            default   => 1.30 + ($age - 33) * 0.06,
        };

        // stamina 50 = neutro; cada ponto acima/abaixo move ±0.005
        $staminaFactor = max(0.75, min(1.25, 1.00 - ($stam - 50) * 0.005));

        return (int) round(self::BASE_DEGRADATION * $ageFactor * $staminaFactor);
    }

    /**
     * Recuperação por rodada de descanso:
     *   base × fator_stamina × fator_idade
     *
     * Alta stamina = recuperação mais rápida.
     * Jovens se recuperam mais rápido que veteranos.
     */
    private function recoveryAmount(LeaguePlayer $player): int
    {
        $staminaFactor = 0.60 + ($player->stamina / 100) * 0.40;  // 0.60–1.00

        $ageFactor = match (true) {
            $player->age < 23 => 1.20,
            $player->age < 30 => 1.00,
            $player->age < 34 => 0.85,
            default           => 0.70,
        };

        return (int) round(self::BASE_RECOVERY * $staminaFactor * $ageFactor);
    }

    /**
     * Verifica e aplica lesão se o dado cair dentro da probabilidade de risco.
     * Retorna true se o jogador foi lesionado.
     */
    private function rollInjury(LeaguePlayer $player, int $round): bool
    {
        $risk = $this->injuryRisk($player);

        if ($risk === 0.0) {
            return false;
        }

        // random_int(1,1000)/1000 para granularidade de 0.1%
        if ((random_int(1, 1000) / 1000) > $risk) {
            return false;
        }

        $this->injurePlayer($player, $round);
        return true;
    }

    /**
     * Lesiona o jogador por 1–8 rodadas, dependendo da gravidade.
     * Quanto menor a fitness no momento da lesão, mais rodadas fora.
     */
    private function injurePlayer(LeaguePlayer $player, int $round): void
    {
        $roundsOut = match (true) {
            $player->fitness < 10 => random_int(5, 8),
            $player->fitness < 25 => random_int(3, 6),
            $player->fitness < 40 => random_int(2, 4),
            default               => random_int(1, 3),
        };

        $player->update([
            'status'        => 'injured',
            'injured_until' => $round + $roundsOut,
            'fitness'       => 0,
        ]);
    }
}
