<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CityController extends Controller
{
    public function index(): View
    {
        $cities = City::with('country')->orderBy('state')->orderBy('name')->paginate(30);

        return view('admin.cities.index', compact('cities'));
    }

    public function create(): View
    {
        return view('admin.cities.create', [
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'state'      => ['nullable', 'string', 'max:10'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
        ]);

        City::create($data);

        return redirect()->route('admin.cities')->with('success', 'Cidade criada com sucesso.');
    }

    public function edit(City $city): View
    {
        return view('admin.cities.edit', [
            'city'      => $city,
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, City $city): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'state'      => ['nullable', 'string', 'max:10'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
        ]);

        $city->update($data);

        return redirect()->route('admin.cities')->with('success', 'Cidade atualizada com sucesso.');
    }

    public function destroy(City $city): RedirectResponse
    {
        if ($city->teams()->exists()) {
            return back()->with('error', "Não é possível remover: {$city->name} possui times vinculados.");
        }

        $city->delete();

        return redirect()->route('admin.cities')->with('success', 'Cidade removida.');
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

            $row = array_combine($header, $line);

            $name  = trim($row['name'] ?? '');
            $state = strtoupper(trim($row['state'] ?? '')) ?: null;

            if ($name === '') {
                $errors[] = "Linha " . ($i + 2) . ": name é obrigatório.";
                continue;
            }

            $countryCode = strtoupper(trim($row['country_code'] ?? ''));
            $countryId   = $countryIndex[$countryCode] ?? null;

            // Evita duplicatas silenciosamente
            $exists = City::where('name', $name)
                ->where('state', $state)
                ->where('country_id', $countryId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            City::create([
                'name'       => $name,
                'state'      => $state,
                'country_id' => $countryId,
            ]);

            $imported++;
        }

        $message = "{$imported} cidade(s) importada(s).";
        if ($skipped)  $message .= " {$skipped} duplicata(s) ignorada(s).";
        if ($errors)   $message .= ' Erros: ' . implode(' | ', $errors);

        return redirect()->route('admin.cities')->with('success', $message);
    }
}
