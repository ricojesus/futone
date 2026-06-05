<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\LeagueTeam;
use App\Services\GlobalRoundService;
use App\Services\LeagueGeneratorService;
use App\Services\SeasonTransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeagueController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        // IDs de ligas onde o usuário é dono
        $ownerIds = League::where('owner_id', $userId)->pluck('id');

        // IDs de ligas onde o usuário tem um time (jogador)
        $playerIds = LeagueTeam::where('user_id', $userId)->pluck('league_id');

        // IDs de ligas onde o usuário está no lobby aguardando sorteio
        $lobbyIds = LeagueMember::where('user_id', $userId)->pluck('league_id');

        $allIds = $ownerIds->concat($playerIds)->concat($lobbyIds)->unique();

        $leagues = League::whereIn('id', $allIds)
            ->with(['competitions', 'owner', 'leagueTeams'])
            ->latest()
            ->get()
            ->map(function (League $league) use ($userId) {
                // Adiciona metadados de papel do usuário para filtro na view
                $league->userIsOwner  = $league->owner_id === $userId;
                $league->userIsPlayer = $league->leagueTeams
                    ->contains('user_id', $userId);
                return $league;
            });

        return view('leagues.index', compact('leagues'));
    }

    public function create()
    {
        return view('leagues.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'access'          => 'required|in:public,private',
            'season'          => 'required|integer|min:1900|max:2200',
            'team_assignment' => 'required|in:manual,auto',
            'max_seasons'     => 'nullable|integer|min:1|max:20',
        ]);

        $league = DB::transaction(function () use ($validated) {
            return League::create([
                'name'            => $validated['name'],
                'slug'            => Str::slug($validated['name']) . '-' . Str::lower(Str::random(5)),
                'owner_id'        => auth()->id(),
                'type'            => $validated['access'],
                'invite_code'     => $validated['access'] === 'private' ? Str::upper(Str::random(8)) : null,
                'status'          => League::STATUS_WAITING,
                'season'          => (int) $validated['season'],
                'season_start'    => (int) $validated['season'],
                'team_assignment' => $validated['team_assignment'],
                'max_seasons'     => $validated['max_seasons'] ?? null,
            ]);
        });

        return redirect()->route('leagues.show', $league)
            ->with('success', "Liga \"{$league->name}\" criada! Agora convide seus amigos.");
    }

    public function show(League $league)
    {
        $league->load(['competitions.state', 'competitions.teams', 'leagueTeams.team', 'owner', 'members.user']);

        $isOwner  = $league->owner_id === auth()->id();

        return view('leagues.show', compact('league', 'isOwner'));
    }

    public function generate(Request $request, League $league, LeagueGeneratorService $generator)
    {
        abort_unless(auth()->id() === $league->owner_id, 403);
        abort_unless($league->competitions()->count() === 0, 409, 'Esta liga já possui competições.');

        try {
            $result = $generator->generateForLeague($league);
        } catch (\Throwable $e) {
            return redirect()->route('leagues.show', $league)
                ->with('error', 'Erro ao gerar competições: ' . $e->getMessage());
        }

        $total = count($result['state']) + count($result['national']);

        // Inicia a liga automaticamente ao gerar as competições
        if ($league->isWaiting()) {
            $league->update([
                'status'     => League::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);
            $league->competitions()->update(['status' => 'in_progress']);
        }

        return redirect()->route('leagues.show', $league)
            ->with('success', "{$total} competições geradas com sucesso!");
    }

    public function start(Request $request, League $league)
    {
        abort_unless(auth()->id() === $league->owner_id, 403);
        abort_unless($league->isWaiting(), 409, 'Liga já foi iniciada.');

        $league->update([
            'status'     => League::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $league->competitions()->update(['status' => 'in_progress']);

        return redirect()->route('leagues.show', $league)
            ->with('success', 'Liga iniciada!');
    }

    /**
     * Avança uma rodada em todas as competições da fase atual da liga.
     * O dono pode chamar quantas vezes quiser em sequência.
     */
    public function advanceWeek(League $league, GlobalRoundService $globalRound)
    {
        abort_unless(auth()->id() === $league->owner_id, 403);
        abort_unless($league->isInProgress(), 409, 'Liga não está em andamento.');

        // Bloqueia se há partida ao vivo pendente (intervalo aguardando)
        if ($globalRound->hasPendingLive($league)) {
            // Redireciona para a partida ao vivo
            $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
                ->where('user_id', auth()->id())
                ->first();

            if ($myLeagueTeam) {
                $halftimeMatch = \App\Models\CompetitionMatch::whereHas('competition', fn($q) =>
                    $q->where('league_id', $league->id)
                )
                ->where('status', 'halftime')
                ->whereHas('homeTeam', fn($q) => $q->where('league_team_id', $myLeagueTeam->id))
                ->orWhereHas('awayTeam', fn($q) => $q->where('league_team_id', $myLeagueTeam->id))
                ->first();

                if ($halftimeMatch) {
                    $competition = $halftimeMatch->competition;
                    return redirect(route('matches.halftime', [$league, $competition, $halftimeMatch]));
                }
            }

            return back()->with('info', 'Há uma partida ao vivo aguardando o intervalo.');
        }

        $result = $globalRound->advance($league);

        // Redireciona para partida ao vivo se o usuário tem um jogo no intervalo
        if ($result['liveMatchUrl']) {
            return redirect($result['liveMatchUrl']);
        }

        if ($result['phaseCompleted'] && $result['nextPhase']) {
            $phaseLabels = [
                'state'    => 'Fase Estadual',
                'copa'     => 'Copa do Brasil',
                'national' => 'Brasileirão',
                'finished' => 'Temporada',
            ];

            $nextLabel = $phaseLabels[$result['nextPhase']] ?? $result['nextPhase'];

            if ($result['nextPhase'] === 'finished') {
                return redirect()->route('leagues.season-summary', $league)
                    ->with('success', 'Temporada encerrada! Veja o resumo.');
            }

            return redirect()->route('leagues.show', $league)
                ->with('success', "Fase estadual concluída! {$nextLabel} criado automaticamente.");
        }

        $msg = $result['competitionsAdvanced'] > 0
            ? "{$result['competitionsAdvanced']} competições avançaram para a próxima rodada."
            : 'Nenhuma competição disponível para avançar.';

        return redirect()->route('leagues.show', $league)->with('success', $msg);
    }

    public function seasonSummary(League $league, SeasonTransitionService $transitionService)
    {
        $league->load(['competitions']);

        $allFinished = $league->competitions->isNotEmpty()
            && $league->competitions->every(fn($c) => $c->status === Competition::STATUS_FINISHED);

        abort_unless($allFinished, 403, 'Nem todas as competições foram encerradas.');

        $transitions = $transitionService->calculateTransitions($league);
        $isOwner     = $league->owner_id === auth()->id();
        $nextYear    = $league->season + 1;

        return view('leagues.season-summary', compact('league', 'transitions', 'isOwner', 'nextYear'));
    }

    public function advanceSeason(Request $request, League $league, SeasonTransitionService $transitionService)
    {
        abort_unless(auth()->id() === $league->owner_id, 403);

        $transitionService->advanceSeason($league);

        return redirect()->route('leagues.show', $league)
            ->with('success', "Temporada {$league->season} iniciada com sucesso!");
    }
}
