<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StateController extends Controller
{
    public function index(): View
    {
        $states = State::with('country')->orderBy('country_id')->orderBy('code')->paginate(50);

        return view('admin.states.index', compact('states'));
    }

    public function create(): View
    {
        return view('admin.states.create', [
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'code'       => ['required', 'string', 'max:10'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
        ]);

        $data['code'] = strtoupper($data['code']);

        State::create($data);

        return redirect()->route('admin.states')->with('success', 'Estado criado com sucesso.');
    }

    public function edit(State $state): View
    {
        return view('admin.states.edit', [
            'state'     => $state,
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, State $state): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'code'       => ['required', 'string', 'max:10'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
        ]);

        $data['code'] = strtoupper($data['code']);

        $state->update($data);

        return redirect()->route('admin.states')->with('success', 'Estado atualizado com sucesso.');
    }

    public function destroy(State $state): RedirectResponse
    {
        if ($state->teams()->exists()) {
            return back()->with('error', "Não é possível remover: {$state->name} possui times vinculados.");
        }

        $state->delete();

        return redirect()->route('admin.states')->with('success', 'Estado removido.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $lines  = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $header = array_map('strtolower', array_map('trim', array_shift($lines)));

        $countryIndex = Country::pluck('id', 'code')->map(fn($id) => (string) $id)->toArray();

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($lines as $i => $line) {
            if (count($line) !== count($header)) {
                $errors[] = "Linha " . ($i + 2) . ": número de colunas inválido.";
                continue;
            }

            $row  = array_combine($header, $line);
            $name = trim($row['name'] ?? '');
            $code = strtoupper(trim($row['code'] ?? ''));

            if ($name === '' || $code === '') {
                $errors[] = "Linha " . ($i + 2) . ": name e code são obrigatórios.";
                continue;
            }

            $countryCode = strtoupper(trim($row['country_code'] ?? ''));
            $countryId   = $countryIndex[$countryCode] ?? null;

            if (State::where('code', $code)->where('country_id', $countryId)->exists()) {
                $skipped++;
                continue;
            }

            State::create([
                'name'       => $name,
                'code'       => $code,
                'country_id' => $countryId,
            ]);

            $imported++;
        }

        $message = "{$imported} estado(s) importado(s).";
        if ($skipped) $message .= " {$skipped} duplicata(s) ignorada(s).";
        if ($errors)  $message .= ' Erros: ' . implode(' | ', $errors);

        return redirect()->route('admin.states')->with('success', $message);
    }
}
