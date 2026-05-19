<?php

namespace App\Services;

use App\Models\LeagueMatch;
use App\Models\LeaguePlayer;
use App\Models\LeagueTeam;

class FormService
{
    /** Fator de decaimento por rodada em direção à média (1.00) */
    private const DECAY = 0.95;

    private const WIN_DELTA  =  0.03;
    private const DRAW_DELTA =  0.00;
    private const LOSS_DELTA = -0.03;

    private const MIN_FORM = 0.85;
    private const MAX_FORM = 1.15;

    /**
     * Atualiza a forma de todos os jogadores ativos dos dois times
     * após uma partida ser finalizada.
     *
     * Fluxo por jogador:
     *   1. Decaimento: form = form × 0.95 + 1.00 × 0.05  (regressão à média)
     *   2. Delta:      vitória +0.03 | empate ±0 | derrota −0.03
     *   3. Clamp:      [0.85, 1.15]
     */
    public function updateAfterMatch(LeagueMatch $match): void
    {
        if (! $match->isFinished()) {
            return;
        }

        [$homeResult, $awayResult] = $this->resolveResults($match);

        $this->applyToTeam($match->homeTeam, $homeResult);
        $this->applyToTeam($match->awayTeam, $awayResult);
    }

    /**
     * Aplica apenas o decaimento, sem delta de resultado.
     * Usado quando um time está de folga em uma rodada (bye week).
     */
    public function decayTeam(LeagueTeam $team): void
    {
        $this->applyToTeam($team, 'none');
    }

    /**
     * Retorna o label de forma para exibição na UI.
     *
     * 1.10–1.15 → "Em Alta"
     * 1.04–1.09 → "Boa Fase"
     * 0.96–1.03 → "Regular"
     * 0.90–0.95 → "Má Fase"
     * 0.85–0.89 → "Em Baixa"
     */
    public function formLabel(float $factor): string
    {
        return match (true) {
            $factor >= 1.10 => 'Em Alta',
            $factor >= 1.04 => 'Boa Fase',
            $factor >= 0.96 => 'Regular',
            $factor >= 0.90 => 'Má Fase',
            default         => 'Em Baixa',
        };
    }

    /**
     * Cor Tailwind associada ao label de forma (para badges na UI).
     */
    public function formColor(float $factor): string
    {
        return match (true) {
            $factor >= 1.10 => 'emerald',
            $factor >= 1.04 => 'sky',
            $factor >= 0.96 => 'slate',
            $factor >= 0.90 => 'amber',
            default         => 'red',
        };
    }

    // ── Internos ─────────────────────────────────────────────────────

    private function applyToTeam(LeagueTeam $team, string $result): void
    {
        $delta = match ($result) {
            'win'  => self::WIN_DELTA,
            'loss' => self::LOSS_DELTA,
            'draw' => self::DRAW_DELTA,
            default => 0.00, // 'none' → só decaimento
        };

        // Atualiza em lote para performance — calcula novo valor por jogador
        // e usa update individual (form é por jogador, não por time)
        $team->players()
            ->where('status', 'active')
            ->each(function (LeaguePlayer $player) use ($delta) {
                $newForm = $this->computeNewForm((float) $player->form_factor, $delta);
                $player->update(['form_factor' => $newForm]);
            });
    }

    private function computeNewForm(float $current, float $delta): float
    {
        // 1. Decaimento em direção a 1.00
        $decayed = ($current * self::DECAY) + (1.00 * (1 - self::DECAY));

        // 2. Aplica resultado
        $newForm = $decayed + $delta;

        // 3. Clamp
        return round(max(self::MIN_FORM, min(self::MAX_FORM, $newForm)), 2);
    }

    private function resolveResults(LeagueMatch $match): array
    {
        if ($match->home_score > $match->away_score) {
            return ['win', 'loss'];
        }

        if ($match->home_score < $match->away_score) {
            return ['loss', 'win'];
        }

        return ['draw', 'draw'];
    }
}
