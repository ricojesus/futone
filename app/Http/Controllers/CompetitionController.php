<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\Team;
use Illuminate\Http\Request;

class CompetitionController extends Controller
{
    /**
     * Página da competição: tabela de classificação + agenda de partidas.
     */
    public function show(League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);

        $competition->load(['state', 'teams.team', 'teams.user']);

        // Partidas agrupadas por rodada
        $matchesByRound = $competition->matches()
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('round')
            ->orderBy('created_at')
            ->get()
            ->groupBy('round');

        // Time do usuário nesta competição (se houver)
        $myTeam = $competition->teams->firstWhere('user_id', auth()->id());

        // Classificação: times ordenados por pontos (simples por enquanto)
        $standings = $competition->teams
            ->sortByDesc('points')
            ->sortByDesc('wins')
            ->values();

        return view('leagues.competitions.show', compact(
            'league', 'competition', 'matchesByRound', 'myTeam', 'standings'
        ));
    }

    /**
     * Formulário para escolher time numa competição específica.
     */
    public function join(League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);
        $this->guardEntry($league, $competition);

        $coaches = Coach::orderBy('name')->get();
        $teams   = $this->availableTeams($competition);

        return view('leagues.competitions.join', compact('league', 'competition', 'teams', 'coaches'));
    }

    /**
     * Inscreve o usuário num time da competição.
     */
    public function joinStore(Request $request, League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);
        $this->guardEntry($league, $competition);

        $validated = $request->validate([
            'team_id'  => 'required|uuid|exists:teams,id',
            'coach_id' => 'nullable|uuid|exists:coaches,id',
        ]);

        if ($competition->teams()->where('team_id', $validated['team_id'])->exists()) {
            return back()->withErrors(['team_id' => 'Este time já está inscrito nesta competição.']);
        }

        $team     = Team::findOrFail($validated['team_id']);
        $compTeam = $this->createCompetitionTeam($competition, $team, $validated['coach_id'] ?? null);

        return redirect()->route('competitions.show', [$league, $competition])
            ->with('success', "{$compTeam->name} inscrito com sucesso! Boa sorte!");
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function guardEntry(League $league, Competition $competition): void
    {
        if (! $competition->isWaiting()) {
            redirect()->route('competitions.show', [$league, $competition])
                ->with('error', 'As inscrições desta competição já foram encerradas.')
                ->throwResponse();
        }

        if ($competition->teams()->where('user_id', auth()->id())->exists()) {
            redirect()->route('competitions.show', [$league, $competition])
                ->with('info', 'Você já está inscrito nesta competição.')
                ->throwResponse();
        }
    }

    private function availableTeams(Competition $competition)
    {
        $enrolledIds = $competition->teams()->whereNotNull('team_id')->pluck('team_id');

        return Team::whereNotIn('id', $enrolledIds)
            ->orderBy('name')
            ->get();
    }

    private function createCompetitionTeam(Competition $competition, Team $team, ?string $coachId): CompetitionTeam
    {
        return CompetitionTeam::create([
            'competition_id' => $competition->id,
            'team_id'        => $team->id,
            'user_id'        => auth()->id(),
            'coach_id'       => $coachId,
            'name'           => $team->name,
            'satisfaction'   => 50,
            'tolerance'      => $team->tolerance ?? 30,
            'budget'         => 5_000_000,
            'points'         => 0,
            'wins'           => 0,
            'draws'          => 0,
            'losses'         => 0,
            'goals_for'      => 0,
            'goals_against'  => 0,
        ]);
    }
}
