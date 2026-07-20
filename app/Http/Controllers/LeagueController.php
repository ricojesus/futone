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
            $league = League::create([
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

            // Liga de sorteio: o dono já entra na fila do lobby — ele também joga
            if ($league->isAutoAssignment()) {
                LeagueMember::create([
                    'league_id' => $league->id,
                    'user_id'   => $league->owner_id,
                    'status'    => LeagueMember::STATUS_WAITING,
                ]);
            }

            return $league;
        });

        return redirect()->route('leagues.show', $league)
            ->with('success', "Liga \"{$league->name}\" criada! Agora convide seus amigos.");
    }

    public function show(League $league)
    {
        $league->load(['competitions.state', 'competitions.teams', 'leagueTeams.team', 'owner', 'members.user']);

        $userId  = auth()->id();
        $isOwner = $league->owner_id === $userId;

        // O Escritório é a home da liga para o técnico (spec 005).
        // O dono mantém a página clássica (administração); ?classic=1 escapa do redirect.
        $userTeam  = $league->leagueTeams->firstWhere('user_id', $userId);
        $isFired   = $league->members->contains(
            fn ($m) => $m->user_id === $userId && $m->status === LeagueMember::STATUS_FIRED
        );

        if ($league->isInProgress() && ! $isOwner && ($userTeam || $isFired) && ! request()->boolean('classic')) {
            return redirect()->route('leagues.office', $league);
        }

        $unreadMessages = \App\Models\LeagueMessage::where('league_id', $league->id)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return view('leagues.show', compact('league', 'isOwner', 'unreadMessages'));
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

        // Liga de sorteio: sorteia quem estiver na fila antes de começar —
        // depois de iniciada, o sorteio do lobby não roda mais (spec 005 / fix lobby)
        $drawn = $league->isAutoAssignment()
            ? app(\App\Services\LobbyService::class)->drawWaitingMembers($league)['assigned']
            : 0;

        // Inicia a liga automaticamente ao gerar as competições
        if ($league->isWaiting()) {
            $league->update([
                'status'     => League::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);
            $league->competitions()->update(['status' => 'in_progress']);
        }

        $msg = "{$total} competições geradas com sucesso!";
        if ($drawn > 0) {
            $msg .= " {$drawn} técnico(s) sorteado(s) para seus times.";
        }

        return redirect()->route('leagues.show', $league)->with('success', $msg);
    }

    public function start(Request $request, League $league)
    {
        abort_unless(auth()->id() === $league->owner_id, 403);
        abort_unless($league->isWaiting(), 409, 'Liga já foi iniciada.');

        // Liga de sorteio: ninguém pode ficar na fila depois que a liga começa
        $drawn = $league->isAutoAssignment()
            ? app(\App\Services\LobbyService::class)->drawWaitingMembers($league)['assigned']
            : 0;

        $league->update([
            'status'     => League::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $league->competitions()->update(['status' => 'in_progress']);

        $msg = 'Liga iniciada!';
        if ($drawn > 0) {
            $msg .= " {$drawn} técnico(s) sorteado(s) para seus times.";
        }

        return redirect()->route('leagues.show', $league)->with('success', $msg);
    }

    /**
     * Avança uma rodada em todas as competições da fase atual da liga.
     * O dono pode chamar quantas vezes quiser em sequência.
     */
    public function advanceWeek(League $league, GlobalRoundService $globalRound)
    {
        abort_unless(auth()->id() === $league->owner_id, 403);
        abort_unless($league->isInProgress(), 409, 'Liga não está em andamento.');

        // O time do PRÓPRIO dono precisa estar escalado antes de avançar —
        // outros humanos sem escalação jogam no 4-4-2 automático (decisão 2026-07-14)
        $ownTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($ownTeam) {
            $lineup   = $ownTeam->activeLineup();
            $starters = $lineup ? $lineup->lineupPlayers()->where('is_starter', true)->count() : 0;

            if ($starters < 11) {
                return redirect()->route('leagues.lineup.edit', [$league, $ownTeam])
                    ->with('error', 'Escale seus 11 titulares antes de avançar a rodada — seu time jogaria com escalação automática.');
            }
        }

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

            return redirect()->route('leagues.office', $league)
                ->with('success', "Fase estadual concluída! {$nextLabel} criado automaticamente.");
        }

        $msg = $result['competitionsAdvanced'] > 0
            ? "{$result['competitionsAdvanced']} competições avançaram para a próxima rodada."
            : 'Nenhuma competição disponível para avançar.';

        return redirect()->route('leagues.office', $league)->with('success', $msg);
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
