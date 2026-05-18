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

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $lines  = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $header = array_map('strtolower', array_map('trim', array_shift($lines)));

        // Indexa países por code para lookup rápido
        $countryIndex = Country::pluck('id', 'code')->map(fn($id) => (string) $id)->toArray();

        $imported = 0;
        $errors   = [];

        foreach ($lines as $i => $line) {
            if (count($line) !== count($header)) {
                $errors[] = "Linha " . ($i + 2) . ": número de colunas inválido.";
                continue;
            }

            $row = array_combine($header, $line);

            $name = trim($row['name'] ?? '');
            if ($name === '') {
                $errors[] = "Linha " . ($i + 2) . ": name é obrigatório.";
                continue;
            }

            $tolerance = isset($row['tolerance']) && is_numeric(trim($row['tolerance']))
                ? max(1, min(100, (int) trim($row['tolerance'])))
                : 50;

            $countryCode = strtoupper(trim($row['country_code'] ?? ''));
            $countryId   = $countryIndex[$countryCode] ?? null;

            Team::create([
                'name'       => $name,
                'city'       => trim($row['city'] ?? '') ?: null,
                'country_id' => $countryId,
                'tolerance'  => $tolerance,
            ]);

            $imported++;
        }

        $message = "{$imported} time(s) importado(s) com sucesso.";
        if ($errors) {
            $message .= ' Erros: ' . implode(' | ', $errors);
        }

        return redirect()->route('admin.teams')->with('success', $message);
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
