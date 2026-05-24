<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueChampionship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeagueController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $leagues = League::where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('teams', fn ($q2) => $q2->where('user_id', $user->id));
            })
            ->with(['championships', 'teams', 'owner'])
            ->latest()
            ->get();

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
            'max_teams'       => 'required|integer|min:2|max:32',
            'season'          => 'required|integer|min:1900|max:2200',
            'team_assignment' => 'required|in:choice,random',
        ]);

        $league = DB::transaction(function () use ($validated) {
            $league = League::create([
                'name'            => $validated['name'],
                'slug'            => Str::slug($validated['name']) . '-' . Str::lower(Str::random(5)),
                'owner_id'        => auth()->id(),
                'state_id'        => null,
                'type'            => $validated['access'],
                'invite_code'     => Str::upper(Str::random(8)),
                'max_teams'       => (int) $validated['max_teams'],
                'team_assignment' => $validated['team_assignment'],
                'status'          => 'waiting',
                'season'          => (int) $validated['season'],
            ]);

            // Formato padrão: pontos corridos, ida e volta
            $n            = (int) $validated['max_teams'];
            $roundsPerLeg = ($n % 2 === 0) ? ($n - 1) : $n;
            $totalRounds  = $roundsPerLeg * 2; // ida + volta

            LeagueChampionship::create([
                'league_id'       => $league->id,
                'championship_id' => null,
                'name'            => $league->name,
                'type'            => 'league',   // pontos corridos
                'legs'            => 'double',   // ida e volta
                'teams_count'     => $n,
                'status'          => 'waiting',
                'current_round'   => 0,
                'total_rounds'    => $totalRounds,
            ]);

            return $league;
        });

        return redirect()->route('leagues.show', $league)
            ->with('success', "Liga \"{$league->name}\" criada! Agora convide os times.");
    }

    public function show(League $league)
    {
        $league->load(['championships', 'teams.user', 'teams.coach', 'teams.team', 'owner']);

        $championship = $league->championships->first();
        $userTeam     = $league->teams->firstWhere('user_id', auth()->id());
        $isOwner      = $league->owner_id === auth()->id();
        $canJoin      = $league->isWaiting()
            && is_null($userTeam)
            && $league->teams->count() < $league->max_teams;
        $canStart     = $isOwner
            && $league->isWaiting()
            && $league->teams->count() >= 2;

        return view('leagues.show', compact(
            'league', 'championship', 'userTeam', 'isOwner', 'canJoin', 'canStart'
        ));
    }

    public function start(Request $request, League $league)
    {
        abort_unless(auth()->id() === $league->owner_id, 403);
        abort_unless($league->isWaiting(), 409, 'Liga já foi iniciada.');
        abort_unless($league->teams()->count() >= 2, 422, 'Mínimo de 2 times é necessário.');

        $league->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        // Marca o campeonato da liga como em andamento
        $league->championships()->update(['status' => 'in_progress']);

        // TODO: Gerar calendário de partidas (game engine)

        return redirect()->route('leagues.show', $league)
            ->with('success', 'Liga iniciada! O calendário será gerado em breve.');
    }
}
