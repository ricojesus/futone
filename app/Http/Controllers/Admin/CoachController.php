<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachController extends Controller
{
    public function index(): View
    {
        $coaches = Coach::with('country')->orderBy('name')->paginate(20);

        return view('admin.coaches.index', compact('coaches'));
    }

    public function create(): View
    {
        return view('admin.coaches.create', [
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'photo'      => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('coaches', 'public');
        }

        Coach::create($data);

        return redirect()->route('admin.coaches')->with('success', 'Treinador criado com sucesso.');
    }

    public function edit(Coach $coach): View
    {
        return view('admin.coaches.edit', [
            'coach'     => $coach,
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Coach $coach): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'photo'      => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('coaches', 'public');
        }

        $coach->update($data);

        return redirect()->route('admin.coaches')->with('success', 'Treinador atualizado com sucesso.');
    }

    public function destroy(Coach $coach): RedirectResponse
    {
        if ($coach->photo) {
            \Storage::disk('public')->delete($coach->photo);
        }

        $coach->delete();

        return redirect()->route('admin.coaches')->with('success', 'Treinador removido.');
    }
}
