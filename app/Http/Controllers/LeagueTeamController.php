<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeagueTeamController extends Controller
{
    // ── Formulário de entrada ────────────────────────────────────────

    /**
     * Exibe a tela de entrada na liga.
     *
     * Sorteio  → mostra confirmação com o time que será sorteado.
     * Escolha  → exibe grade de times disponíveis para escolha.
     */
    public function create(League $league)
    {
        $this->guardEntry($league);

        $coaches = Coach::orderBy('name')->get();

        if ($league->usesRandomAssignment()) {
            $available = $this->availableTeams($league);

            if ($available->isEmpty()) {
                return redirect()->route('leagues.show', $league)
                    ->with('error', 'Não há times disponíveis para sorteio no momento.');
            }

            return view('leagues.teams.draw', compact('league', 'coaches', 'available'));
        }

        // Modo escolha livre
        $teams = $this->availableTeams($league);

        return view('leagues.teams.create', compact('league', 'teams', 'coaches'));
    }

    // ── Persistência ────────────────────────────────────────────────

    /**
     * Inscreve o usuário na liga.
     *
     * Sorteio  → ignora team_id do request e sorteia aleatoriamente.
     * Escolha  → usa o team_id enviado pelo formulário.
     */
    public function store(Request $request, League $league)
    {
        $this->guardEntry($league);

        return $league->usesRandomAssignment()
            ? $this->storeRandom($request, $league)
            : $this->storeChoice($request, $league);
    }

    // ── Fluxo: sorteio ───────────────────────────────────────────────

    private function storeRandom(Request $request, League $league): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'coach_id' => 'nullable|uuid|exists:coaches,id',
        ]);

        $leagueTeam = DB::transaction(function () use ($league, $validated) {
            // Busca times disponíveis dentro da transação para evitar race condition
            $available = $this->availableTeams($league);

            if ($available->isEmpty()) {
                return null;
            }

            $team = $available->random();

            return $this->createLeagueTeam($league, $team, $validated['coach_id'] ?? null);
        });

        if (! $leagueTeam) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'Não foi possível sortear um time. Tente novamente.');
        }

        return redirect()->route('leagues.show', $league)
            ->with('success', "🎲 Sorteio realizado! Seu time é o {$leagueTeam->name}. Boa sorte!");
    }

    // ── Fluxo: escolha livre ─────────────────────────────────────────

    private function storeChoice(Request $request, League $league): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'team_id'  => 'required|uuid|exists:teams,id',
            'coach_id' => 'nullable|uuid|exists:coaches,id',
        ]);

        if ($league->teams()->where('team_id', $validated['team_id'])->exists()) {
            return back()->withErrors(['team_id' => 'Este time já está inscrito nesta liga.']);
        }

        $team = Team::findOrFail($validated['team_id']);

        $leagueTeam = $this->createLeagueTeam($league, $team, $validated['coach_id'] ?? null);

        return redirect()->route('leagues.show', $league)
            ->with('success', "{$leagueTeam->name} inscrito com sucesso!");
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Valida pré-condições de entrada na liga.
     * Redireciona automaticamente se alguma falhar.
     */
    private function guardEntry(League $league): void
    {
        if (! $league->isWaiting()) {
            redirect()->route('leagues.show', $league)
                ->with('error', 'As inscrições desta liga já foram encerradas.')
                ->throwResponse();
        }

        if ($league->teams()->where('user_id', auth()->id())->exists()) {
            redirect()->route('leagues.show', $league)
                ->with('info', 'Você já está inscrito nesta liga.')
                ->throwResponse();
        }

        if ($league->teams()->count() >= $league->max_teams) {
            redirect()->route('leagues.show', $league)
                ->with('error', 'Esta liga já está com todas as vagas preenchidas.')
                ->throwResponse();
        }
    }

    /**
     * Retorna os times ainda não inscritos nesta liga.
     */
    private function availableTeams(League $league)
    {
        $enrolledIds = $league->teams()->whereNotNull('team_id')->pluck('team_id');

        return Team::whereNotIn('id', $enrolledIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Cria o registro LeagueTeam para um usuário + time + liga.
     */
    private function createLeagueTeam(League $league, Team $team, ?string $coachId): LeagueTeam
    {
        return LeagueTeam::create([
            'league_id'     => $league->id,
            'team_id'       => $team->id,
            'user_id'       => auth()->id(),
            'coach_id'      => $coachId,
            'name'          => $team->name,
            'satisfaction'  => 50,
            'tolerance'     => $team->tolerance ?? 30,
            'budget'        => 5_000_000,
            'points'        => 0,
            'wins'          => 0,
            'draws'         => 0,
            'losses'        => 0,
            'goals_for'     => 0,
            'goals_against' => 0,
        ]);
    }
}
