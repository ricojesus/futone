<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'potential'  => ['nullable', 'integer', 'min:1', 'max:99'],
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

        // Lookups rápidos
        $countryIndex = Country::pluck('id', 'code')->map(fn($id) => (string) $id)->toArray();

        // Times indexados por slug para resolver team_name → team_id
        $teamIndex = Team::whereNotNull('slug')
            ->pluck('id', 'slug')
            ->map(fn($id) => (string) $id)
            ->toArray();

        $imported = 0;
        $errors   = [];

        foreach ($lines as $i => $line) {
            if (count($line) !== count($header)) {
                $errors[] = "Linha " . ($i + 2) . ": número de colunas inválido.";
                continue;
            }

            $row = array_combine($header, array_map('trim', $line));

            $name     = $row['name'] ?? '';
            $position = $row['position'] ?? '';

            if ($name === '' || $position === '') {
                $errors[] = "Linha " . ($i + 2) . ": name e position são obrigatórios.";
                continue;
            }

            if (! array_key_exists($position, Player::$positions)) {
                $errors[] = "Linha " . ($i + 2) . ": position '{$position}' inválida.";
                continue;
            }

            $countryCode = strtoupper($row['country_code'] ?? '');
            $countryId   = $countryIndex[$countryCode] ?? null;

            // Resolve time pelo slug gerado a partir de team_name
            $teamId = null;
            if (! empty($row['team_name'])) {
                $slug   = Str::slug($row['team_name']);
                $teamId = $teamIndex[$slug] ?? null;
            }

            $potential = is_numeric($row['potential'] ?? null)
                ? max(1, min(99, (int) $row['potential']))
                : 75;

            Player::updateOrCreate(
                [
                    'name'    => $name,
                    'team_id' => $teamId,
                ],
                [
                    'position'   => $position,
                    'team_id'    => $teamId,
                    'country_id' => $countryId,
                    'age'        => is_numeric($row['age'] ?? null) ? (int) $row['age'] : null,
                    'strength'   => is_numeric($row['strength'] ?? null) ? (int) $row['strength'] : 50,
                    'stamina'    => is_numeric($row['stamina'] ?? null) ? (int) $row['stamina'] : 100,
                    'potential'  => $potential,
                ]
            );

            $imported++;
        }

        $message = "{$imported} jogador(es) importado(s) com sucesso.";
        if ($errors) {
            $message .= ' Erros: ' . implode(' | ', $errors);
        }

        return redirect()->route('admin.players')->with('success', $message);
    }
}
