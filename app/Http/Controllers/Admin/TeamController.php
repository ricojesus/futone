<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $teams = Team::with('country')->orderBy('name')->paginate(20);

        return view('admin.teams.index', compact('teams'));
    }

    public function create(): View
    {
        return view('admin.teams.create', [
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'city'       => ['nullable', 'string', 'max:100'],
            'tolerance'  => ['required', 'integer', 'min:1', 'max:100'],
            'badge'      => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('badge')) {
            $data['badge'] = $request->file('badge')->store('teams', 'public');
        }

        Team::create($data);

        return redirect()->route('admin.teams')->with('success', 'Time criado com sucesso.');
    }

    public function edit(Team $team): View
    {
        return view('admin.teams.edit', [
            'team'      => $team,
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'city'       => ['nullable', 'string', 'max:100'],
            'tolerance'  => ['required', 'integer', 'min:1', 'max:100'],
            'badge'      => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('badge')) {
            if ($team->badge) {
                \Storage::disk('public')->delete($team->badge);
            }
            $data['badge'] = $request->file('badge')->store('teams', 'public');
        }

        $team->update($data);

        return redirect()->route('admin.teams')->with('success', 'Time atualizado com sucesso.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        if ($team->badge) {
            \Storage::disk('public')->delete($team->badge);
        }

        $team->delete();

        return redirect()->route('admin.teams')->with('success', 'Time removido.');
    }
}
