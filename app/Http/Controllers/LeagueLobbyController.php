<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\LeagueTeam;
use App\Services\SatisfactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeagueLobbyController extends Controller
{
    /**
     * Jogador entra na fila do lobby (liga com sorteio automático).
     */
    public function join(Request $request, League $league)
    {
        abort_unless($league->isAutoAssignment(), 404);
        abort_unless($league->isWaiting(), 409, 'Esta liga já foi iniciada.');

        $userId = auth()->id();

        // Já é dono — não entra na fila
        if ($league->owner_id === $userId) {
            return redirect()->route('leagues.show', $league)
                ->with('info', 'Você é o dono desta liga.');
        }

        // Já está na fila ou já tem time
        $alreadyMember = LeagueMember::where('league_id', $league->id)
            ->where('user_id', $userId)
            ->exists();

        $alreadyHasTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyMember || $alreadyHasTeam) {
            return redirect()->route('leagues.show', $league)
                ->with('info', 'Você já está inscrito nesta liga.');
        }

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id'   => $userId,
            'status'    => LeagueMember::STATUS_WAITING,
        ]);

        return redirect()->route('leagues.show', $league)
            ->with('success', 'Você entrou na fila! Aguarde o dono da liga realizar o sorteio.');
    }

    /**
     * Dono da liga sorteia os times para todos os membros na fila.
     */
    public function draw(Request $request, League $league)
    {
        abort_unless($league->owner_id === auth()->id(), 403);
        abort_unless($league->isAutoAssignment(), 404);
        abort_unless($league->isWaiting(), 409, 'Esta liga já foi iniciada.');

        $waitingMembers = LeagueMember::where('league_id', $league->id)
            ->where('status', LeagueMember::STATUS_WAITING)
            ->with('user')
            ->get();

        if ($waitingMembers->isEmpty()) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'Nenhum jogador na fila para sortear.');
        }

        // Times CPU disponíveis (sem dono)
        $availableTeams = LeagueTeam::where('league_id', $league->id)
            ->whereNull('user_id')
            ->whereNotNull('team_id')
            ->get()
            ->shuffle();

        if ($availableTeams->isEmpty()) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'Não há times disponíveis para sortear.');
        }

        // Sorteia: cada membro recebe um time aleatório
        $membersShuffled = $waitingMembers->shuffle();
        $assigned = 0;

        $satisfactionService = app(SatisfactionService::class);

        DB::transaction(function () use ($membersShuffled, $availableTeams, &$assigned, $league, $satisfactionService) {
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
                    $satisfactionService->releaseCoachToPool(
                        $league->id,
                        $team->id,
                        $previousCoachId
                    );
                }

                $member->update(['status' => LeagueMember::STATUS_ASSIGNED]);
                $assigned++;
            }
        });

        $skipped = $waitingMembers->count() - $assigned;
        $msg = "{$assigned} time(s) sorteado(s) com sucesso!";
        if ($skipped > 0) {
            $msg .= " {$skipped} jogador(es) ficaram sem time (times insuficientes).";
        }

        return redirect()->route('leagues.show', $league)->with('success', $msg);
    }
}
