<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\League;
use App\Models\LeagueTeam;

class MatchController extends Controller
{
    public function show(League $league, Competition $competition, CompetitionMatch $match)
    {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($match->competition_id === $competition->id, 404);
        abort_unless($match->status === 'finished', 404, 'Partida ainda não disputada.');

        $match->load(['homeTeam', 'awayTeam']);

        // Time do usuário nesta liga
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        // É uma partida do usuário?
        $isMyMatch = $myLeagueTeam && (
            $match->homeTeam->league_team_id === $myLeagueTeam->id ||
            $match->awayTeam->league_team_id === $myLeagueTeam->id
        );

        $side = null;
        if ($isMyMatch) {
            $side = $match->homeTeam->league_team_id === $myLeagueTeam->id ? 'home' : 'away';
        }

        // Outros jogos da mesma rodada
        $roundMatches = $competition->matches()
            ->where('round', $match->round)
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        // replay=1 → modo animado (vindo do advanceRound)
        // sem parâmetro → modo estático de detalhes
        $replayMode = request()->boolean('replay');

        return view('leagues.competitions.matches.show', compact(
            'league', 'competition', 'match',
            'myLeagueTeam', 'isMyMatch', 'side', 'roundMatches', 'replayMode'
        ));
    }
}
