<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Competition;
use App\Models\League;
use App\Models\LeagueTeam;
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

        $this->guardEntry($league);

        $coaches = Coach::orderBy('name')->get();

        // Times CPU disponíveis na liga (sem dono ainda)
        $teams = $this->availableTeams($league);

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

        $this->guardEntry($league);

        return $this->storeChoice($request, $league, $competition);
    }

    // ── Fluxo: escolha livre ─────────────────────────────────────────

    private function storeChoice(Request $request, League $league, Competition $competition): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'team_id'  => 'required|uuid|exists:teams,id',
            'coach_id' => 'nullable|uuid|exists:coaches,id',
        ]);

        $team = Team::findOrFail($validated['team_id']);

        // Verifica se existe um LeagueTeam CPU para este time nesta liga
        $leagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('team_id', $team->id)
            ->whereNull('user_id')
            ->first();

        if (! $leagueTeam) {
            return back()->withErrors(['team_id' => 'Este time não está disponível nesta liga.']);
        }

        // Assume o controle do LeagueTeam
        DB::transaction(function () use ($leagueTeam, $validated) {
            $leagueTeam->update([
                'user_id'  => auth()->id(),
                'coach_id' => $validated['coach_id'] ?? null,
            ]);
        });

        return redirect()->route('leagues.show', $league)
            ->with('success', "{$leagueTeam->name} inscrito com sucesso!");
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
     * Valida pré-condições de entrada na liga.
     */
    private function guardEntry(League $league): void
    {
        $alreadyHasTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyHasTeam) {
            redirect()->route('leagues.show', $league)
                ->with('info', 'Você já está inscrito nesta liga.')
                ->throwResponse();
        }
    }

    /**
     * Retorna os times CPU disponíveis para assumir nesta liga.
     */
    private function availableTeams(League $league)
    {
        $availableTeamIds = LeagueTeam::where('league_id', $league->id)
            ->whereNull('user_id')
            ->whereNotNull('team_id')
            ->pluck('team_id');

        return Team::whereIn('id', $availableTeamIds)
            ->orderBy('name')
            ->get();
    }
}
