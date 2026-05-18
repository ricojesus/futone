<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function index(): View
    {
        $players = Player::with('country')->orderBy('name')->paginate(20);

        return view('admin.players.index', compact('players'));
    }

    public function create(): View
    {
        return view('admin.players.create', [
            'positions' => Player::$positions,
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'position'   => ['required', 'in:goalkeeper,defender,midfielder,forward'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'age'        => ['nullable', 'integer', 'min:15', 'max:50'],
            'strength'   => ['required', 'integer', 'min:1', 'max:99'],
            'stamina'    => ['required', 'integer', 'min:1', 'max:100'],
            'photo'      => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('players', 'public');
        }

        Player::create($data);

        return redirect()->route('admin.players')->with('success', 'Jogador criado com sucesso.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $lines  = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $header = array_map('strtolower', array_map('trim', array_shift($lines)));

        // Indexa países por code para lookup rápido no CSV
        $countryIndex = Country::pluck('id', 'code')->map(fn($id) => (string) $id)->toArray();

        $fields   = ['name', 'position', 'country_code', 'age', 'strength', 'stamina'];
        $imported = 0;
        $errors   = [];

        foreach ($lines as $i => $line) {
            $row = array_combine($header, $line);

            if (!$row) {
                $errors[] = "Linha " . ($i + 2) . ": formato inválido.";
                continue;
            }

            $data = array_intersect_key($row, array_flip($fields));

            if (empty($data['name']) || empty($data['position'])) {
                $errors[] = "Linha " . ($i + 2) . ": name e position são obrigatórios.";
                continue;
            }

            if (!array_key_exists($data['position'], Player::$positions)) {
                $errors[] = "Linha " . ($i + 2) . ": position '{$data['position']}' inválida.";
                continue;
            }

            $countryCode = strtoupper(trim($data['country_code'] ?? ''));
            $countryId   = $countryIndex[$countryCode] ?? null;

            Player::create([
                'name'       => trim($data['name']),
                'position'   => trim($data['position']),
                'country_id' => $countryId,
                'age'        => is_numeric($data['age'] ?? null) ? (int) $data['age'] : null,
                'strength'   => is_numeric($data['strength'] ?? null) ? (int) $data['strength'] : 50,
                'stamina'    => is_numeric($data['stamina'] ?? null) ? (int) $data['stamina'] : 100,
            ]);

            $imported++;
        }

        $message = "{$imported} jogador(es) importado(s) com sucesso.";
        if ($errors) {
            $message .= ' Erros: ' . implode(' | ', $errors);
        }

        return redirect()->route('admin.players')->with('success', $message);
    }
}
