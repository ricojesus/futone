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
                  ->orWhereHas('competitions.teams', fn ($q2) => $q2->where('user_id', $user->id));
            })
            ->with(['competitions'])
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard', compact('myLeagues'));
    }
}
