<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Models\Team;
use App\Services\CompetitionRoundService;
use App\Services\CopaBrasilService;
use App\Services\LiveMatchSimulator;
use App\Services\MatchSimulator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionController extends Controller
{
    /**
     * Página da competição: tabela de classificação + agenda de partidas.
     */
    public function show(League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);

        $competition->load(['state', 'teams.leagueTeam']);

        // Partidas agrupadas por rodada
        $matchesByRound = $competition->matches()
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('round')
            ->orderBy('created_at')
            ->get()
            ->groupBy('round');

        // LeagueTeam do usuário nesta liga (se houver)
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        // CompetitionTeam (stats) correspondente nesta competição específica
        $myTeam = $myLeagueTeam
            ? $competition->teams->firstWhere('league_team_id', $myLeagueTeam->id)
            : null;

        // É dono da liga?
        $isOwner = $league->owner_id === auth()->id();

        // Classificação: times ordenados por pontos
        $standings = $competition->teams
            ->sortByDesc('points')
            ->sortByDesc('wins')
            ->values();

        // Artilheiros: jogadores dos times desta competição, com gols, top 10
        $leagueTeamIds = $competition->teams->pluck('league_team_id')->filter();
        $topScorers = CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)
            ->where('goals_scored', '>', 0)
            ->with('leagueTeam')
            ->orderByDesc('goals_scored')
            ->limit(10)
            ->get();

        // Verifica se o usuário tem uma partida atualmente no intervalo
        $myHalftimeMatch = null;
        $myHalftimeUrl   = null;
        if ($myLeagueTeam && $myTeam) {
            $myHalftimeMatch = \App\Models\CompetitionMatch::where('competition_id', $competition->id)
                ->where('status', 'halftime')
                ->where(function ($q) use ($myTeam) {
                    $q->where('home_team_id', $myTeam->id)
                      ->orWhere('away_team_id', $myTeam->id);
                })
                ->first();

            if ($myHalftimeMatch) {
                $myHalftimeUrl = route('matches.halftime', [$league, $competition, $myHalftimeMatch]);
            }
        }

        // Dados específicos para competições knockout (Copa do Brasil)
        $bracketPhases = collect();
        $phaseNames    = [];
        if ($competition->isKnockout()) {
            $allMatches = $competition->matches()
                ->with(['homeTeam', 'awayTeam'])
                ->orderBy('round')
                ->orderBy('bracket_slot')
                ->get();

            // Agrupa por fase (par de rodadas) → slot → [leg1, leg2]
            $bracketPhases = $allMatches
                ->groupBy(fn($m) => (int) ceil($m->round / 2))   // fase = ceil(round/2)
                ->map(fn($phaseMatches) =>
                    $phaseMatches->groupBy('bracket_slot')
                        ->map(fn($slotMatches) => [
                            'leg1' => $slotMatches->firstWhere('leg', 1),
                            'leg2' => $slotMatches->firstWhere('leg', 2),
                        ])
                );

            $phaseNames = CopaBrasilService::PHASE_NAMES;
        }

        return view('leagues.competitions.show', compact(
            'league', 'competition', 'matchesByRound', 'myLeagueTeam', 'myTeam', 'standings', 'isOwner', 'topScorers',
            'myHalftimeMatch', 'myHalftimeUrl', 'bracketPhases', 'phaseNames'
        ));
    }

    /**
     * Retorna o estado atual da competição para polling do cliente.
     * Resposta leve: rodada atual, status e (se o usuário tiver time) URL da sua partida na rodada mais recente.
     */
    public function roundStatus(League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);

        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        $myMatchUrl = null;

        if ($myLeagueTeam && $competition->current_round > 0) {
            $myCompTeam = CompetitionTeam::where('competition_id', $competition->id)
                ->where('league_team_id', $myLeagueTeam->id)
                ->first();

            if ($myCompTeam) {
                $myMatch = CompetitionMatch::where('competition_id', $competition->id)
                    ->where('round', $competition->current_round)
                    ->where('status', 'finished')
                    ->where(function ($q) use ($myCompTeam) {
                        $q->where('home_team_id', $myCompTeam->id)
                          ->orWhere('away_team_id', $myCompTeam->id);
                    })->first();

                if ($myMatch) {
                    $myMatchUrl = route('matches.show', [$league, $competition, $myMatch, 'replay' => 1]);
                }
            }
        }

        return response()->json([
            'current_round' => $competition->current_round,
            'status'        => $competition->status,
            'my_match_url'  => $myMatchUrl,
        ]);
    }

    /**
     * Formulário para escolher time na liga.
     * Exibe todos os times CPU disponíveis em qualquer competição da liga.
     */
    public function join(League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);
        $this->guardEntry($league, $competition);

        $coaches = Coach::orderBy('name')->get();
        $teams   = $this->availableTeams($league, $competition);

        return view('leagues.competitions.join', compact('league', 'competition', 'teams', 'coaches'));
    }

    /**
     * Assume o controle de um time na liga inteira.
     *
     * Atualiza o LeagueTeam CPU com aquele team_id nesta liga,
     * transferindo o user_id para o usuário logado. Assim, o jogador
     * gerencia o mesmo clube em todas as competições em que ele participa.
     */
    public function joinStore(Request $request, League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);
        $this->guardEntry($league, $competition);

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

        // Assume o controle do LeagueTeam (único registro de identidade)
        DB::transaction(function () use ($leagueTeam, $validated) {
            $leagueTeam->update([
                'user_id'  => auth()->id(),
                'coach_id' => $validated['coach_id'] ?? null,
            ]);
        });

        $compsCount = $leagueTeam->competitionTeams()->count();
        $msg = $compsCount > 1
            ? "{$team->name} é seu! Você gerencia este clube em {$compsCount} competições desta liga."
            : "{$team->name} inscrito com sucesso! Boa sorte!";

        return redirect()->route('competitions.show', [$league, $competition])
            ->with('success', $msg);
    }

    /**
     * Simula todas as partidas da próxima rodada desta competição.
     * Só o dono da liga pode acionar.
     *
     * Partidas CPU × CPU  → simuladas instantaneamente (MatchSimulator)
     * Partidas CPU × Humano → primeiro tempo simulado, aguarda intervalo (LiveMatchSimulator)
     */
    public function advanceRound(
        Request                  $request,
        League                   $league,
        Competition              $competition,
        CompetitionRoundService  $roundService,
    ) {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($league->owner_id === auth()->id(), 403);
        abort_unless($competition->isInProgress(), 409, 'Competição não está em andamento.');

        if (($competition->current_round + 1) > $competition->total_rounds) {
            return back()->with('error', 'Todas as rodadas já foram executadas.');
        }

        $result = $roundService->advance($competition);

        if ($result['liveCount'] === 0 && $result['cpuCount'] === 0) {
            return back()->with('info', 'Todas as partidas desta rodada já foram simuladas.');
        }

        // Redireciona para partida ao vivo se o usuário tem um time que jogou
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($myLeagueTeam && $result['liveMatches']->isNotEmpty()) {
            $myCompTeam = CompetitionTeam::where('competition_id', $competition->id)
                ->where('league_team_id', $myLeagueTeam->id)
                ->first();

            if ($myCompTeam) {
                $myMatch = $result['liveMatches']->first(function ($m) use ($myCompTeam) {
                    return $m->home_team_id === $myCompTeam->id
                        || $m->away_team_id === $myCompTeam->id;
                });

                if ($myMatch) {
                    return redirect(route('matches.halftime', [$league, $competition, $myMatch]));
                }
            }
        }

        $msg = $result['liveCount'] > 0
            ? "{$result['cpuCount']} partidas simuladas. {$result['liveCount']} partida(s) aguardando o intervalo."
            : "Rodada {$result['nextRound']} simulada! {$result['cpuCount']} partidas jogadas.";

        return redirect()->route('competitions.show', [$league, $competition])->with('success', $msg);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function guardEntry(League $league, Competition $competition): void
    {
        if ($competition->isFinished()) {
            redirect()->route('competitions.show', [$league, $competition])
                ->with('error', 'Esta competição já foi encerrada.')
                ->throwResponse();
        }

        $alreadyHasTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyHasTeam) {
            redirect()->route('leagues.show', $league)
                ->with('info', 'Você já gerencia um time nesta liga.')
                ->throwResponse();
        }
    }

    private function availableTeams(League $league, Competition $competition)
    {
        // Somente os LeagueTeams CPU que participam DESTA competição específica
        $availableLeagueTeamIds = CompetitionTeam::where('competition_id', $competition->id)
            ->pluck('league_team_id');

        $availableTeamIds = LeagueTeam::whereIn('id', $availableLeagueTeamIds)
            ->where('league_id', $league->id)
            ->whereNull('user_id')
            ->whereNotNull('team_id')
            ->pluck('team_id');

        return Team::whereIn('id', $availableTeamIds)
            ->orderBy('name')
            ->get();
    }

}
