<?php

namespace App\Http\Controllers;

use App\Models\League;

class UserDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $myLeagues = League::where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('leagueTeams', fn ($q2) => $q2->where('user_id', $user->id))
                  // Demitidos (LeagueMember 'fired') continuam vendo a liga — spec 005
                  ->orWhereHas('members', fn ($q2) => $q2->where('user_id', $user->id));
            })
            ->with(['competitions'])
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard', compact('myLeagues'));
    }
}
