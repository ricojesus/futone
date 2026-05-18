<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\Country;
use App\Models\Player;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users'     => User::count(),
            'players'   => Player::count(),
            'countries' => Country::count(),
            'coaches'   => Coach::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
