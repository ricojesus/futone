<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Championship;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChampionshipController extends Controller
{
    public function index(): View
    {
        $championships = Championship::with(['country', 'state'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.championships.index', compact('championships'));
    }

    public function create(): View
    {
        return view('admin.championships.create', [
            'countries' => Country::orderBy('name')->get(),
            'states'    => State::with('country')->orderBy('code')->get(),
            'types'     => Championship::$types,
            'legs'      => Championship::$legs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'country_id'        => ['nullable', 'uuid', 'exists:countries,id'],
            'state_id'          => ['nullable', 'uuid', 'exists:states,id'],
            'type'              => ['required', 'in:league,cup,mixed'],
            'legs'              => ['required', 'in:single,double'],
            'teams_count'       => ['required', 'integer', 'min:2', 'max:64'],
            'promotion_spots'   => ['nullable', 'integer', 'min:1', 'max:32'],
            'relegation_spots'  => ['nullable', 'integer', 'min:1', 'max:32'],
        ]);

        Championship::create($data);

        return redirect()->route('admin.championships')->with('success', 'Campeonato criado com sucesso.');
    }

    public function edit(Championship $championship): View
    {
        return view('admin.championships.edit', [
            'championship' => $championship,
            'countries'    => Country::orderBy('name')->get(),
            'states'       => State::with('country')->orderBy('code')->get(),
            'types'        => Championship::$types,
            'legs'         => Championship::$legs,
        ]);
    }

    public function update(Request $request, Championship $championship): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'country_id'        => ['nullable', 'uuid', 'exists:countries,id'],
            'state_id'          => ['nullable', 'uuid', 'exists:states,id'],
            'type'              => ['required', 'in:league,cup,mixed'],
            'legs'              => ['required', 'in:single,double'],
            'teams_count'       => ['required', 'integer', 'min:2', 'max:64'],
            'promotion_spots'   => ['nullable', 'integer', 'min:1', 'max:32'],
            'relegation_spots'  => ['nullable', 'integer', 'min:1', 'max:32'],
        ]);

        $championship->update($data);

        return redirect()->route('admin.championships')->with('success', 'Campeonato atualizado com sucesso.');
    }

    public function destroy(Championship $championship): RedirectResponse
    {
        if ($championship->leagueChampionships()->exists()) {
            return back()->with('error', "Não é possível remover: {$championship->name} está em uso em ligas ativas.");
        }

        $championship->delete();

        return redirect()->route('admin.championships')->with('success', 'Campeonato removido.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $lines  = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $header = array_map('strtolower', array_map('trim', array_shift($lines)));

        $countryIndex = Country::pluck('id', 'code')->map(fn($id) => (string) $id)->toArray();
        $stateIndex   = State::pluck('id', 'code')->map(fn($id) => (string) $id)->toArray();

        $validTypes = ['league', 'cup', 'mixed'];
        $validLegs  = ['single', 'double'];

        $imported = 0;
        $errors   = [];

        foreach ($lines as $i => $line) {
            if (count($line) !== count($header)) {
                $errors[] = "Linha " . ($i + 2) . ": número de colunas inválido.";
                continue;
            }

            $row  = array_combine($header, $line);
            $name = trim($row['name'] ?? '');

            if ($name === '') {
                $errors[] = "Linha " . ($i + 2) . ": name é obrigatório.";
                continue;
            }

            $type = trim($row['type'] ?? 'league');
            $legs = trim($row['legs'] ?? 'double');

            if (!in_array($type, $validTypes)) {
                $errors[] = "Linha " . ($i + 2) . ": type '{$type}' inválido.";
                continue;
            }

            if (!in_array($legs, $validLegs)) {
                $errors[] = "Linha " . ($i + 2) . ": legs '{$legs}' inválido.";
                continue;
            }

            $countryCode = strtoupper(trim($row['country_code'] ?? ''));
            $stateCode   = strtoupper(trim($row['state_code'] ?? ''));

            Championship::create([
                'name'             => $name,
                'country_id'       => $countryIndex[$countryCode] ?? null,
                'state_id'         => $stateIndex[$stateCode] ?? null,
                'type'             => $type,
                'legs'             => $legs,
                'teams_count'      => is_numeric($row['teams_count'] ?? null) ? (int) $row['teams_count'] : 20,
                'promotion_spots'  => is_numeric($row['promotion_spots'] ?? null) ? (int) $row['promotion_spots'] : null,
                'relegation_spots' => is_numeric($row['relegation_spots'] ?? null) ? (int) $row['relegation_spots'] : null,
            ]);

            $imported++;
        }

        $message = "{$imported} campeonato(s) importado(s).";
        if ($errors) $message .= ' Erros: ' . implode(' | ', $errors);

        return redirect()->route('admin.championships')->with('success', $message);
    }
}
