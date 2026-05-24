<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeagueTeamController extends Controller
{
    // ── Formulário de entrada ────────────────────────────────────────

    /**
     * Exibe a tela de escolha de time dentro de uma competição da liga.
     *
     * Rota original: /leagues/{league}/join → leagues.teams.create
     * Usa a primeira competição 'waiting' da liga para inscrição.
     */
    public function create(League $league)
    {
        $competition = $this->findOpenCompetition($league);

        if (! $competition) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'Não há competições abertas para inscrição nesta liga.');
        }

        $this->guardEntry($league, $competition);

        $coaches = Coach::orderBy('name')->get();

        // Modo escolha livre (único modo suportado na nova arquitetura por padrão)
        $teams = $this->availableTeams($competition);

        return view('leagues.teams.create', compact('league', 'competition', 'teams', 'coaches'));
    }

    // ── Persistência ────────────────────────────────────────────────

    public function store(Request $request, League $league)
    {
        $competition = $this->findOpenCompetition($league);

        if (! $competition) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'Não há competições abertas para inscrição nesta liga.');
        }

        $this->guardEntry($league, $competition);

        return $this->storeChoice($request, $league, $competition);
    }

    // ── Fluxo: escolha livre ─────────────────────────────────────────

    private function storeChoice(Request $request, League $league, Competition $competition): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'team_id'  => 'required|uuid|exists:teams,id',
            'coach_id' => 'nullable|uuid|exists:coaches,id',
        ]);

        if ($competition->teams()->where('team_id', $validated['team_id'])->exists()) {
            return back()->withErrors(['team_id' => 'Este time já está inscrito nesta competição.']);
        }

        $team = Team::findOrFail($validated['team_id']);

        $compTeam = $this->createCompetitionTeam($competition, $team, $validated['coach_id'] ?? null);

        return redirect()->route('leagues.show', $league)
            ->with('success', "{$compTeam->name} inscrito com sucesso!");
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Encontra a primeira competição aberta (waiting) da liga para inscrição.
     */
    private function findOpenCompetition(League $league): ?Competition
    {
        return $league->competitions()->where('status', 'waiting')->first();
    }

    /**
     * Valida pré-condições de entrada na competição.
     */
    private function guardEntry(League $league, Competition $competition): void
    {
        if (! $competition->isWaiting()) {
            redirect()->route('leagues.show', $league)
                ->with('error', 'As inscrições desta competição já foram encerradas.')
                ->throwResponse();
        }

        if ($competition->teams()->where('user_id', auth()->id())->exists()) {
            redirect()->route('leagues.show', $league)
                ->with('info', 'Você já está inscrito nesta competição.')
                ->throwResponse();
        }
    }

    /**
     * Retorna os times ainda não inscritos nesta competição.
     */
    private function availableTeams(Competition $competition)
    {
        $enrolledIds = $competition->teams()->whereNotNull('team_id')->pluck('team_id');

        return Team::whereNotIn('id', $enrolledIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Cria o registro CompetitionTeam para um usuário + time + competição.
     */
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
