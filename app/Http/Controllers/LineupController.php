<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionLineup;
use App\Models\League;
use App\Models\LeagueTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LineupController extends Controller
{
    /**
     * Exibe o formulário de escalação do time na liga.
     *
     * Route: GET /leagues/{league}/teams/{leagueTeam}/lineup
     * The {leagueTeam} route parameter resolves to a LeagueTeam record.
     */
    public function edit(League $league, LeagueTeam $leagueTeam)
    {
        $this->authorizeManager($league, $leagueTeam);

        $leagueTeam->loadMissing('team');

        // Escalação padrão ativa (round = 0)
        $lineup = $leagueTeam->lineups()
            ->where('status', 'active')
            ->where('round', 0)
            ->with(['lineupPlayers.competitionPlayer'])
            ->first();

        // Mapa playerId → role para preencher a UI
        $currentStarters = $lineup
            ? $lineup->lineupPlayers
                ->where('is_starter', true)
                ->mapWithKeys(fn($lp) => [$lp->competition_player_id => $lp->role])
            : collect();

        // Jogadores disponíveis ordenados por posição natural e força
        $players = $leagueTeam->players()
            ->whereIn('status', ['active', 'injured'])
            ->orderByRaw("FIELD(position, 'goalkeeper','defender','midfielder','forward')")
            ->orderByDesc('strength')
            ->get();

        // Competição de retorno: prioriza ?back=uuid (quando vindo da página da competição)
        // fallback: primeira competição em andamento do time
        $backCompetitionId = request()->query('back');
        $competition = ($backCompetitionId
            ? Competition::find($backCompetitionId)
            : null)
            ?? $leagueTeam->competitionTeams()
                ->whereHas('competition', fn($q) => $q->where('status', 'in_progress'))
                ->first()
                ?->competition;

        $backUrl = $competition
            ? route('competitions.show', [$league, $competition])
            : route('leagues.show', $league);

        return view('leagues.lineups.edit', compact(
            'league', 'leagueTeam', 'lineup', 'currentStarters', 'players', 'competition', 'backUrl'
        ));
    }

    /**
     * Salva a escalação padrão do time.
     */
    public function update(Request $request, League $league, LeagueTeam $leagueTeam)
    {
        $this->authorizeManager($league, $leagueTeam);

        $formation = $request->input('formation', '4-4-2');
        $starters  = $request->input('starters', []); // {player_uuid: role}

        // ── Valida formação ──────────────────────────────────────────
        abort_unless(
            array_key_exists($formation, CompetitionLineup::FORMATIONS),
            422,
            "Formação «{$formation}» não suportada."
        );

        $slots = CompetitionLineup::FORMATIONS[$formation];

        // ── Valida total de jogadores ────────────────────────────────
        $errors = [];

        if (count($starters) !== 11) {
            $errors[] = 'A escalação precisa ter exatamente 11 jogadores.';
        }

        $counts = ['goalkeeper' => 0, 'defender' => 0, 'midfielder' => 0, 'forward' => 0];
        foreach ($starters as $playerId => $role) {
            if (! array_key_exists($role, $counts)) {
                $errors[] = "Função inválida: {$role}.";
                continue;
            }
            $counts[$role]++;
        }

        if ($counts['goalkeeper'] !== 1) {
            $errors[] = 'É preciso ter exatamente 1 goleiro.';
        }
        foreach (['defender', 'midfielder', 'forward'] as $role) {
            $required = $slots[$role];
            if ($counts[$role] !== $required) {
                $labels = ['defender' => 'defensor(es)', 'midfielder' => 'meio-campista(s)', 'forward' => 'atacante(s)'];
                $errors[] = "A formação {$formation} exige {$required} {$labels[$role]}.";
            }
        }

        if (! empty($errors)) {
            return back()->withInput()->withErrors($errors);
        }

        // ── Verifica que todos os jogadores pertencem ao time ────────
        $teamPlayerIds = $leagueTeam->players()
            ->whereIn('status', ['active'])
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        foreach (array_keys($starters) as $id) {
            if (! in_array((string) $id, $teamPlayerIds, true)) {
                return back()->withErrors(["Jogador inválido: {$id}"]);
            }
        }

        // ── Persiste ─────────────────────────────────────────────────
        DB::transaction(function () use ($league, $leagueTeam, $formation, $starters) {
            $lineup = $leagueTeam->lineups()->updateOrCreate(
                ['round' => 0, 'status' => 'active'],
                [
                    'formation'      => $formation,
                    'competition_id' => null, // league-level default lineup
                ]
            );

            $lineup->lineupPlayers()->delete();

            $slotCounters = ['goalkeeper' => 0, 'defender' => 0, 'midfielder' => 0, 'forward' => 0];

            foreach ($starters as $playerId => $role) {
                $slotCounters[$role]++;

                $lineup->lineupPlayers()->create([
                    'competition_player_id' => $playerId,
                    'role'                  => $role,
                    'slot'                  => $slotCounters[$role],
                    'is_starter'            => true,
                ]);
            }
        });

        return redirect()
            ->route('leagues.lineup.edit', [$league, $leagueTeam])
            ->with('success', 'Escalação salva com sucesso!');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function authorizeManager(League $league, LeagueTeam $leagueTeam): void
    {
        // Verify the league team belongs to this league
        abort_unless(
            $leagueTeam->league_id === $league->id,
            404
        );
        abort_unless(
            $leagueTeam->user_id === auth()->id(),
            403,
            'Você não gerencia este time.'
        );
    }
}
