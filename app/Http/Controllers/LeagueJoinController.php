<?php

namespace App\Http\Controllers;

use App\Models\League;
use Illuminate\Http\Request;

class LeagueJoinController extends Controller
{
    /** Tela de busca: ligas públicas + campo para código de convite */
    public function create()
    {
        $publicLeagues = League::where('type', 'public')
            ->where('status', 'waiting')
            ->with(['competitions', 'owner'])
            ->withCount('competitions')
            ->latest()
            ->get();

        return view('leagues.join', compact('publicLeagues'));
    }

    /** Valida o código de convite e redireciona para o lobby da liga */
    public function store(Request $request)
    {
        $request->validate([
            'invite_code' => 'required|string|min:6|max:12',
        ]);

        $league = League::where('invite_code', strtoupper(trim($request->invite_code)))
            ->where('status', 'waiting')
            ->first();

        if (! $league) {
            return back()
                ->withErrors(['invite_code' => 'Código inválido ou liga não encontrada.'])
                ->withInput();
        }

        return redirect()->route('leagues.show', $league);
    }
}
