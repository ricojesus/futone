<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class PlayerDevelopmentService
{
    public function __construct(
        private readonly MarketValueService $marketValue,
    ) {}

    /**
     * Processa a virada de ano de uma liga.
     *
     * Para cada jogador da liga:
     *   1. Incrementa a idade (age += 1)
     *   2. Desenvolve ou deteriora strength e stamina conforme idade + potencial
     *   3. Recalcula e persiste o market_value
     *   4. Atualiza o catálogo (players.*) para refletir a evolução
     *
     * Incrementa league.season ao final.
     *
     * @return array{season: int, processed: int, improved: int, declined: int}
     */
    public function processSeason(League $league): array
    {
        $stats = ['season' => $league->season + 1, 'processed' => 0, 'improved' => 0, 'declined' => 0];

        DB::transaction(function () use ($league, &$stats) {
            $players = LeaguePlayer::where('league_id', $league->id)
                ->whereIn('status', ['active', 'injured', 'free_agent'])
                ->get();

            foreach ($players as $player) {
                $result = $this->developPlayer($player);

                $stats['processed']++;
                if ($result === 'improved') $stats['improved']++;
                if ($result === 'declined') $stats['declined']++;
            }

            $league->increment('season');
        });

        return $stats;
    }

    // ── Internos ─────────────────────────────────────────────────────

    /**
     * Desenvolve um jogador individualmente.
     * Retorna 'improved', 'declined' ou 'stable'.
     */
    private function developPlayer(LeaguePlayer $leaguePlayer): string
    {
        $newAge      = $leaguePlayer->age + 1;
        $newStrength = $this->developAttribute($leaguePlayer->strength, $leaguePlayer->potential, $leaguePlayer->age);
        $newStamina  = $this->developAttribute($leaguePlayer->stamina,  $leaguePlayer->potential, $leaguePlayer->age);

        // Atualiza snapshot da liga
        $leaguePlayer->update([
            'age'          => $newAge,
            'strength'     => $newStrength,
            'stamina'      => $newStamina,
            'market_value' => $this->marketValue->calculate(
                $leaguePlayer->forceFill([
                    'age'      => $newAge,
                    'strength' => $newStrength,
                    'stamina'  => $newStamina,
                ])
            ),
        ]);

        // Atualiza catálogo (evolução persiste para ligas futuras)
        if ($leaguePlayer->player_id) {
            Player::where('id', $leaguePlayer->player_id)->update([
                'age'      => $newAge,
                'strength' => $newStrength,
                'stamina'  => $newStamina,
            ]);
        }

        $delta = ($newStrength - $leaguePlayer->strength) + ($newStamina - $leaguePlayer->stamina);

        return match (true) {
            $delta > 0  => 'improved',
            $delta < 0  => 'declined',
            default     => 'stable',
        };
    }

    /**
     * Desenvolve um atributo individual (strength ou stamina) com base na idade.
     *
     * Jovens em desenvolvimento (< 27):
     *   O crescimento é limitado pelo potencial e pela distância até ele.
     *   Quanto mais longe do potencial, maior a chance de ganhar pontos.
     *
     * Auge (27–29): estabilidade com leve acaso de −1.
     *
     * Declínio (30+): queda progressiva, sem importar o potencial.
     *
     * O atributo nunca cai abaixo de 1 nem sobe acima de 99.
     */
    private function developAttribute(int $current, int $potential, int $age): int
    {
        $new = match (true) {
            $age < 19 => $this->grow($current, $potential, maxGain: 3, probability: 1.00),
            $age < 22 => $this->grow($current, $potential, maxGain: 2, probability: 0.90),
            $age < 25 => $this->grow($current, $potential, maxGain: 2, probability: 0.75),
            $age < 27 => $this->grow($current, $potential, maxGain: 1, probability: 0.55),

            // Auge: 80% estável, 20% perde 1
            $age < 30 => $current - (random_int(1, 10) > 8 ? 1 : 0),

            $age < 33 => $this->decline($current, maxLoss: 1, probability: 0.55),
            $age < 35 => $this->decline($current, maxLoss: 1, probability: 1.00),
            $age < 37 => $this->decline($current, maxLoss: 2, probability: 1.00),
            default   => $this->decline($current, maxLoss: 3, probability: 1.00),
        };

        return max(1, min(99, $new));
    }

    /**
     * Tenta crescer um atributo, respeitando o teto do potencial.
     *
     * A probabilidade de crescer é proporcional à distância do potencial:
     * se já está perto do teto, cresce menos frequentemente mesmo sendo jovem.
     */
    private function grow(int $current, int $potential, int $maxGain, float $probability): int
    {
        // Já atingiu o potencial — não cresce mais
        if ($current >= $potential) {
            return $current;
        }

        // Penaliza probabilidade conforme se aproxima do teto
        $gap             = $potential - $current;
        $gapFactor       = min(1.0, $gap / 20); // próximo do teto → menor chance
        $effectiveProb   = $probability * $gapFactor;

        if ((random_int(1, 1000) / 1000) > $effectiveProb) {
            return $current;
        }

        $possibleGain = min($maxGain, $gap);
        return $current + random_int(1, max(1, $possibleGain));
    }

    private function decline(int $current, int $maxLoss, float $probability): int
    {
        if ((random_int(1, 1000) / 1000) > $probability) {
            return $current;
        }

        return $current - random_int(1, max(1, $maxLoss));
    }
}
