<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\LeagueTeam;
use Illuminate\Support\Facades\DB;

/**
 * Sorteio de times do lobby (ligas com team_assignment = auto).
 *
 * Usado pelo botão "Sortear Times" do dono e, automaticamente, ao
 * iniciar/gerar a liga — para que ninguém fique na fila depois que a
 * liga começou (depois disso o sorteio não roda mais).
 */
class LobbyService
{
    public function __construct(
        private readonly SatisfactionService $satisfaction,
    ) {}

    /**
     * Sorteia um time CPU para cada membro na fila.
     *
     * @return array{assigned: int, waiting: int}
     */
    public function drawWaitingMembers(League $league): array
    {
        $waitingMembers = LeagueMember::where('league_id', $league->id)
            ->where('status', LeagueMember::STATUS_WAITING)
            ->get();

        if ($waitingMembers->isEmpty()) {
            return ['assigned' => 0, 'waiting' => 0];
        }

        // Times CPU disponíveis (sem dono)
        $availableTeams = LeagueTeam::where('league_id', $league->id)
            ->whereNull('user_id')
            ->whereNotNull('team_id')
            ->get()
            ->shuffle();

        $membersShuffled = $waitingMembers->shuffle();
        $assigned = 0;

        DB::transaction(function () use ($membersShuffled, $availableTeams, &$assigned, $league) {
            foreach ($membersShuffled as $index => $member) {
                $team = $availableTeams->get($index);

                if (! $team) break; // Mais jogadores do que times disponíveis

                $previousCoachId = $team->coach_id;

                $team->update([
                    'user_id'  => $member->user_id,
                    'coach_id' => null, // humano assume; técnico vai para o mercado
                ]);

                // Libera o técnico padrão do clube para o pool de livres
                if ($previousCoachId) {
                    $this->satisfaction->releaseCoachToPool($league->id, $team->id, $previousCoachId);
                }

                $member->update(['status' => LeagueMember::STATUS_ASSIGNED]);
                $assigned++;
            }
        });

        return ['assigned' => $assigned, 'waiting' => $waitingMembers->count()];
    }
}
