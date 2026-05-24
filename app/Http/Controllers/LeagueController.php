<?php

namespace App\Http\Controllers;

use App\Models\League;
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
        $league->load(['competitions', 'owner']);

        $isOwner  = $league->owner_id === auth()->id();

        return view('leagues.show', compact('league', 'isOwner'));
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
