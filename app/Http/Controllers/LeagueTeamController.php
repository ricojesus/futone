<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Models\Team;
use Illuminate\Http\Request;

class LeagueTeamController extends Controller
{
    /** Formulário de escolha de time para entrar na liga */
    public function create(League $league)
    {
        if (! $league->isWaiting()) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'As inscrições desta liga já foram encerradas.');
        }

        if ($league->teams()->where('user_id', auth()->id())->exists()) {
            return redirect()->route('leagues.show', $league)
                ->with('info', 'Você já está inscrito nesta liga.');
        }

        if ($league->teams()->count() >= $league->max_teams) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'Esta liga já está com todas as vagas preenchidas.');
        }

        $enrolledTeamIds = $league->teams()->whereNotNull('team_id')->pluck('team_id');
        $teams   = Team::whereNotIn('id', $enrolledTeamIds)->orderBy('name')->get();
        $coaches = Coach::orderBy('name')->get();

        return view('leagues.teams.create', compact('league', 'teams', 'coaches'));
    }

    /** Persiste a inscrição do usuário na liga com o time e treinador escolhidos */
    public function store(Request $request, League $league)
    {
        if (! $league->isWaiting()) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'As inscrições desta liga já foram encerradas.');
        }

        if ($league->teams()->where('user_id', auth()->id())->exists()) {
            return redirect()->route('leagues.show', $league)
                ->with('info', 'Você já está inscrito nesta liga.');
        }

        if ($league->teams()->count() >= $league->max_teams) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'Esta liga já está com todas as vagas preenchidas.');
        }

        $validated = $request->validate([
            'team_id'  => 'required|uuid|exists:teams,id',
            'coach_id' => 'nullable|uuid|exists:coaches,id',
        ]);

        if ($league->teams()->where('team_id', $validated['team_id'])->exists()) {
            return back()->withErrors(['team_id' => 'Este time já está inscrito nesta liga.']);
        }

        $team = Team::findOrFail($validated['team_id']);

        LeagueTeam::create([
            'league_id'     => $league->id,
            'team_id'       => $team->id,
            'user_id'       => auth()->id(),
            'coach_id'      => $validated['coach_id'] ?? null,
            'name'          => $team->name,
            'satisfaction'  => 50,
            'tolerance'     => $team->tolerance,
            'budget'        => 5_000_000,
            'points'        => 0,
            'wins'          => 0,
            'draws'         => 0,
            'losses'        => 0,
            'goals_for'     => 0,
            'goals_against' => 0,
        ]);

        return redirect()->route('leagues.show', $league)
            ->with('success', "{$team->name} inscrito com sucesso!");
    }
}
