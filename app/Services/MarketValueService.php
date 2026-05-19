<?php

namespace App\Services;

use App\Models\LeaguePlayer;

class MarketValueService
{
    /**
     * Multiplicadores por posição.
     * Avançados valem mais — maior impacto nos resultados/bilheteria.
     */
    private array $positionMultipliers = [
        'goalkeeper' => 0.90,
        'defender'   => 1.00,
        'midfielder' => 1.15,
        'forward'    => 1.25,
    ];

    /**
     * Calcula o valor de mercado baseado em atributos e idade.
     *
     *   base       = média(strength, stamina) × 10 000
     *   age_factor = curva com pico aos 27 anos
     *   pos_mult   = multiplicador por posição
     *
     *   market_value = base × age_factor × pos_mult
     */
    public function calculate(LeaguePlayer $player): int
    {
        $base         = (($player->strength + $player->stamina) / 2) * 10_000;
        $ageFactor    = $this->ageFactor($player->age);
        $posMultiplier = $this->positionMultipliers[$player->position] ?? 1.00;

        return (int) round($base * $ageFactor * $posMultiplier);
    }

    /**
     * Curva de idade:
     *   < 18       → 0.50  (jovem sem experiência)
     *   18–21      → 0.50 → 1.00  (+0.125/ano)
     *   22–27      → 1.00 → 1.30  (+0.05/ano — anos de ouro)
     *   28–31      → 1.30 → 0.90  (-0.10/ano — início do declínio)
     *   32+        → 0.90 → 0.15  (-0.12/ano, mínimo 0.15)
     */
    public function ageFactor(int $age): float
    {
        return match (true) {
            $age < 18 => 0.50,
            $age < 22 => 0.50 + ($age - 18) * 0.125,
            $age < 28 => 1.00 + ($age - 22) * 0.05,
            $age < 32 => 1.30 - ($age - 28) * 0.10,
            default   => max(0.15, 0.90 - ($age - 32) * 0.12),
        };
    }

    /**
     * Salário sugerido: ~0,3% do valor de mercado por rodada.
     * Referência base para o manager e para a CPU.
     */
    public function suggestedWage(int $marketValue): int
    {
        return (int) round($marketValue * 0.003);
    }

    /**
     * Salário mínimo que o jogador aceita nesta liga.
     * wage_expectation_factor (0.80–1.20) representa a "personalidade" financeira
     * do jogador — sorteado uma vez na criação e desconhecido pelo manager.
     */
    public function minimumWage(LeaguePlayer $player): int
    {
        $marketValue = $player->market_value > 0
            ? $player->market_value
            : $this->calculate($player);

        return (int) round(
            $this->suggestedWage($marketValue) * $player->wage_expectation_factor
        );
    }

    /**
     * Gera um fator de expectativa aleatório: 0.80 a 1.20.
     * Chamado uma vez quando o jogador entra na liga.
     */
    public function randomExpectationFactor(): float
    {
        return random_int(80, 120) / 100;
    }

    /**
     * Recalcula e persiste o market_value de um jogador.
     * Chamado ao final de cada rodada pelo game engine.
     */
    public function refresh(LeaguePlayer $player): int
    {
        $value = $this->calculate($player);
        $player->update(['market_value' => $value]);
        return $value;
    }

    /**
     * Estimativa de salário mínimo sem conhecer o fator exato.
     * Visível ao manager como referência: "aceita a partir de ~R$ X".
     * Usa factor = 1.00 (centro da distribuição).
     */
    public function estimatedMinWage(LeaguePlayer $player): int
    {
        $marketValue = $player->market_value > 0
            ? $player->market_value
            : $this->calculate($player);

        return $this->suggestedWage($marketValue);
    }
}
