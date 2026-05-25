<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Services\LeagueGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeagueController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $leagues = League::where('owner_id', $user->id)
            ->with(['competitions', 'owner'])
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
            'name'   => 'required|string|max:100',
            'access' => 'required|in:public,private',
            'season' => 'required|integer|min:1900|max:2200',
        ]);

        $league = DB::transaction(function () use ($validated) {
            return League::create([
                'name'        => $validated['name'],
                'slug'        => Str::slug($validated['name']) . '-' . Str::lower(Str::random(5)),
                'owner_id'    => auth()->id(),
                'type'        => $validated['access'],
                'invite_code' => $validated['access'] === 'private' ? Str::upper(Str::random(8)) : null,
                'status'      => League::STATUS_WAITING,
                'season'      => (int) $validated['season'],
            ]);
        });

        return redirect()->route('leagues.show', $league)
            ->with('success', "Liga \"{$league->name}\" criada! Agora convide seus amigos.");
    }

    public function show(League $league)
    {
        $league->load(['competitions.state', 'competitions.teams', 'owner']);

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
}
