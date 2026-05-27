<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\State;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;

class TeamController extends Controller
{
    public function index(): View
    {
        $teams = Team::with(['country', 'state'])->orderBy('name')->paginate(20);

        return view('admin.teams.index', compact('teams'));
    }

    public function create(): View
    {
        return view('admin.teams.create', [
            'countries' => Country::orderBy('name')->get(),
            'cities'    => State::orderBy('country_id')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'country_id'       => ['nullable', 'uuid', 'exists:countries,id'],
            'state_id'         => ['nullable', 'uuid', 'exists:states,id'],
            'overall'          => ['nullable', 'integer', 'min:1', 'max:99'],
            'state_division'   => ['nullable', 'in:first,second'],
            'national_division'=> ['nullable', 'in:first,second'],
            'tolerance'        => ['required', 'integer', 'min:1', 'max:100'],
            'fans_base'        => ['nullable', 'integer', 'min:0'],
            'stadium_capacity' => ['nullable', 'integer', 'min:0'],
            'badge'            => ['nullable', 'image', 'max:2048'],
        ]);

        $data['slug'] = Str::slug($data['name']);

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
            'cities'    => State::orderBy('country_id')->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'country_id'       => ['nullable', 'uuid', 'exists:countries,id'],
            'state_id'         => ['nullable', 'uuid', 'exists:states,id'],
            'tolerance'        => ['required', 'integer', 'min:1', 'max:100'],
            'fans_base'        => ['nullable', 'integer', 'min:0'],
            'stadium_capacity' => ['nullable', 'integer', 'min:0'],
            'badge'            => ['nullable', 'image', 'max:2048'],
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

        // Indexa países e estados por lookup rápido
        $countryIndex = Country::pluck('id', 'code')->map(fn($id) => (string) $id)->toArray();
        // Indexa estados por sigla (ex: "SP")
        $stateIndex = State::pluck('id', 'code')->map(fn($id) => (string) $id)->toArray();

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

            $stateCode = strtoupper(trim($row['state'] ?? ''));
            $stateId   = $stateIndex[$stateCode] ?? null;

            // fans_base e stadium_capacity: opcionais no CSV (default 10000 no banco)
            $fansBase = isset($row['fans_base']) && is_numeric(trim($row['fans_base']))
                ? max(0, (int) trim($row['fans_base']))
                : 10000;

            $stadiumCapacity = isset($row['stadium_capacity']) && is_numeric(trim($row['stadium_capacity']))
                ? max(0, (int) trim($row['stadium_capacity']))
                : 10000;

            $overall          = isset($row['overall']) && is_numeric(trim($row['overall']))
                ? max(1, min(99, (int) trim($row['overall'])))
                : 70;

            $stateDivision    = in_array(trim($row['state_division'] ?? ''), ['first', 'second'])
                ? trim($row['state_division'])
                : null;

            $nationalDivision = in_array(trim($row['national_division'] ?? ''), ['first', 'second'])
                ? trim($row['national_division'])
                : null;

            $slug = Str::slug($name);

            Team::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'              => $name,
                    'slug'              => $slug,
                    'state_id'          => $stateId,
                    'country_id'        => $countryId,
                    'overall'           => $overall,
                    'state_division'    => $stateDivision,
                    'national_division' => $nationalDivision,
                    'tolerance'         => $tolerance,
                    'fans_base'         => $fansBase,
                    'stadium_capacity'  => $stadiumCapacity,
                ]
            );

            $imported++;
        }

        $message = "{$imported} time(s) importado(s) com sucesso.";
        if ($errors) {
            $message .= ' Erros: ' . implode(' | ', $errors);
        }

        return redirect()->route('admin.teams')->with('success', $message);
    }

    /**
     * Upload em massa de logos via ZIP.
     * Cada arquivo dentro do ZIP deve ser nomeado com o slug do time.
     * Ex: corinthians.png, sao-paulo.webp, flamengo.jpg
     */
    public function uploadLogos(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:51200'], // 50 MB
        ]);

        $zip = new ZipArchive();
        $zipPath = $request->file('file')->getRealPath();

        if ($zip->open($zipPath) !== true) {
            return redirect()->route('admin.teams')->with('error', 'Arquivo ZIP inválido ou corrompido.');
        }

        $allowed    = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
        $updated    = 0;
        $notFound   = [];
        $skipped    = [];

        // Pré-carrega todos os times indexados por slug
        $teams = Team::whereNotNull('slug')->get()->keyBy('slug');

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename  = $zip->getNameIndex($i);
            $basename  = basename($filename);

            // Ignora diretórios e arquivos ocultos
            if (str_ends_with($filename, '/') || str_starts_with($basename, '.')) {
                continue;
            }

            $ext  = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            $slug = Str::slug(pathinfo($basename, PATHINFO_FILENAME));

            if (! in_array($ext, $allowed)) {
                $skipped[] = $basename . ' (extensão não suportada)';
                continue;
            }

            $team = $teams->get($slug);

            if (! $team) {
                $notFound[] = $slug;
                continue;
            }

            // Armazena logo em storage/public/team-logos/{slug}.{ext}
            $storagePath = "team-logos/{$slug}.{$ext}";
            Storage::disk('public')->put($storagePath, $zip->getFromIndex($i));

            // Remove logo antigo se for diferente
            if ($team->badge && $team->badge !== $storagePath) {
                Storage::disk('public')->delete($team->badge);
            }

            $team->update(['badge' => $storagePath]);
            $updated++;
        }

        $zip->close();

        $msg = "{$updated} logo(s) atualizado(s).";
        if ($notFound) {
            $msg .= ' Slugs não encontrados: ' . implode(', ', array_slice($notFound, 0, 10));
            if (count($notFound) > 10) $msg .= ' e mais ' . (count($notFound) - 10) . '.';
        }
        if ($skipped) {
            $msg .= ' Ignorados: ' . implode(', ', $skipped);
        }

        return redirect()->route('admin.teams')->with('success', $msg);
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
