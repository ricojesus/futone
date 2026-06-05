<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Competition;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Models\Team;
use App\Services\SatisfactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeagueTeamController extends Controller
{
    // ── Perfil público do time (scouting) ────────────────────────────

    public function show(League $league, LeagueTeam $leagueTeam)
    {
        abort_unless($leagueTeam->league_id === $league->id, 404);

        $leagueTeam->loadMissing(['team', 'user', 'coach']);

        // Escalação ativa (padrão ou da rodada atual)
        $lineup = $leagueTeam->lineups()
            ->where('status', 'active')
            ->orderByDesc('round')
            ->with(['lineupPlayers.competitionPlayer'])
            ->first();

        $positionOrder = ['goalkeeper' => 0, 'defender' => 1, 'midfielder' => 2, 'forward' => 3];

        $starters = collect();
        if ($lineup) {
            $starters = $lineup->lineupPlayers
                ->where('is_starter', true)
                ->map(fn($lp) => $lp->competitionPlayer)
                ->filter()
                ->sortBy(fn($p) => $positionOrder[$p->position] ?? 99)
                ->values();
        }

        // Elenco completo ordenado por posição e OVR
        $squad = $leagueTeam->players()
            ->where('status', 'active')
            ->orderByRaw("FIELD(position, 'goalkeeper','defender','midfielder','forward')")
            ->orderByDesc('strength')
            ->get();

        // Stats nas competições desta liga
        $competitionTeams = $leagueTeam->competitionTeams()
            ->with('competition:id,name,competition_type,division')
            ->orderByDesc('points')
            ->get();

        // Time do usuário logado nesta liga (para saber se é "meu time")
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        $isMyTeam = $myLeagueTeam && $myLeagueTeam->id === $leagueTeam->id;

        return view('leagues.teams.show', compact(
            'league', 'leagueTeam', 'lineup', 'starters', 'squad',
            'competitionTeams', 'isMyTeam', 'myLeagueTeam',
        ));
    }

    // ── Formulário de entrada ────────────────────────────────────────

    /**
     * Exibe a tela de escolha de time dentro de uma competição da liga.
     *
     * Rota original: /leagues/{league}/join → leagues.teams.create
     * Usa a primeira competição 'waiting' da liga para inscrição.
     */
    public function create(League $league)
    {
        // Liga com sorteio automático: jogadores entram pelo lobby
        if ($league->isAutoAssignment()) {
            return redirect()->route('leagues.show', $league)
                ->with('info', 'Esta liga usa sorteio automático. Aguarde o dono sortear os times.');
        }

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
        DB::transaction(function () use ($leagueTeam, $validated, $league) {
            $previousCoachId = $leagueTeam->coach_id;

            $leagueTeam->update([
                'user_id'  => auth()->id(),
                'coach_id' => null, // humano é o técnico; coach vai para o mercado
            ]);

            // Libera o técnico padrão do clube para o pool de livres da liga
            if ($previousCoachId) {
                app(SatisfactionService::class)->releaseCoachToPool(
                    $league->id,
                    $leagueTeam->id,
                    $previousCoachId
                );
            }
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
