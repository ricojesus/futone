<?php

namespace App\Services;

use App\Models\CompetitionLineup;
use App\Models\LeagueTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gera e persiste a escalação automática de um time CPU a cada rodada,
 * escolhendo os 11 melhores jogadores disponíveis por posição considerando
 * o desgaste atual (fitness).
 *
 * Sem uma CompetitionLineup persistida, applyFitnessDegradation() nunca
 * encontra titulares para descontar fitness de times CPU — o elenco deles
 * nunca cansava e só recuperava (ver CLAUDE.md § Armadilhas Conhecidas).
 * Persistir a escalação aqui fecha essa lacuna e faz a escolha rodar de
 * verdade por rodada: jogadores mais desgastados perdem "power" e saem
 * do 11 inicial para outros do elenco descansarem.
 */
class CpuLineupService
{
    private const FORMATION = '4-4-2';

    private const QUOTAS = [
        'goalkeeper' => 1,
        'defender'   => 4,
        'midfielder' => 4,
        'forward'    => 2,
    ];

    /**
     * Gera (ou atualiza) a escalação do time para a rodada informada.
     * Não faz nada para times controlados por humano.
     */
    public function generateForRound(LeagueTeam $leagueTeam, int $round): ?CompetitionLineup
    {
        if (! $leagueTeam->isCpu()) {
            return null;
        }

        $eleven = $this->bestEleven($leagueTeam);

        if ($eleven->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($leagueTeam, $round, $eleven) {
            $lineup = $leagueTeam->lineups()->updateOrCreate(
                ['round' => $round, 'status' => 'active'],
                ['formation' => self::FORMATION, 'competition_id' => null],
            );

            $lineup->lineupPlayers()->delete();

            $slotCounters = ['goalkeeper' => 0, 'defender' => 0, 'midfielder' => 0, 'forward' => 0];

            foreach ($eleven as $player) {
                $slotCounters[$player->position]++;

                $lineup->lineupPlayers()->create([
                    'competition_player_id' => $player->id,
                    'role'                  => $player->position,
                    'slot'                  => $slotCounters[$player->position],
                    'is_starter'            => true,
                ]);
            }

            return $lineup;
        });
    }

    /**
     * Melhores jogadores disponíveis por posição, priorizando quem está mais
     * descansado e em melhor forma — mesmo critério de "power" usado na
     * simulação (strength × fitness/100 × form_factor).
     */
    private function bestEleven(LeagueTeam $leagueTeam): Collection
    {
        $active = $leagueTeam->players()->where('status', 'active')->get();

        $power = fn($p) => $p->strength * ($p->fitness / 100) * (float) $p->form_factor;

        $pick = fn(string $position, int $n) => $active
            ->where('position', $position)
            ->sortByDesc($power)
            ->take($n)
            ->values();

        return collect()
            ->merge($pick('goalkeeper', self::QUOTAS['goalkeeper']))
            ->merge($pick('defender',   self::QUOTAS['defender']))
            ->merge($pick('midfielder', self::QUOTAS['midfielder']))
            ->merge($pick('forward',    self::QUOTAS['forward']));
    }
}
