<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jogador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JogadorController extends Controller
{
    public function index(): View
    {
        $jogadores = Jogador::orderBy('nome')->paginate(20);

        return view('admin.jogadores.index', compact('jogadores'));
    }

    public function create(): View
    {
        return view('admin.jogadores.create', [
            'posicoes' => Jogador::$posicoes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'          => ['required', 'string', 'max:100'],
            'posicao'       => ['required', 'in:goleiro,defesa,meio,ataque'],
            'nacionalidade' => ['nullable', 'string', 'max:60'],
            'idade'         => ['nullable', 'integer', 'min:15', 'max:50'],
            'forca'         => ['required', 'integer', 'min:1', 'max:99'],
            'foto'          => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('jogadores', 'public');
        }

        Jogador::create($data);

        return redirect()->route('admin.jogadores')->with('success', 'Jogador criado com sucesso.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $linhas = array_map('str_getcsv', file($request->file('arquivo')->getRealPath()));
        $cabecalho = array_map('strtolower', array_map('trim', array_shift($linhas)));

        $campos = ['nome', 'posicao', 'nacionalidade', 'idade', 'forca'];
        $importados = 0;
        $erros = [];

        foreach ($linhas as $i => $linha) {
            $row = array_combine($cabecalho, $linha);

            if (!$row) {
                $erros[] = "Linha " . ($i + 2) . ": formato inválido.";
                continue;
            }

            $dados = array_intersect_key($row, array_flip($campos));

            if (empty($dados['nome']) || empty($dados['posicao'])) {
                $erros[] = "Linha " . ($i + 2) . ": nome e posição são obrigatórios.";
                continue;
            }

            if (!in_array($dados['posicao'], array_keys(Jogador::$posicoes))) {
                $erros[] = "Linha " . ($i + 2) . ": posição '{$dados['posicao']}' inválida.";
                continue;
            }

            Jogador::create([
                'nome'          => trim($dados['nome']),
                'posicao'       => trim($dados['posicao']),
                'nacionalidade' => trim($dados['nacionalidade'] ?? ''),
                'idade'         => is_numeric($dados['idade'] ?? null) ? (int) $dados['idade'] : null,
                'forca'         => is_numeric($dados['forca'] ?? null) ? (int) $dados['forca'] : 50,
            ]);

            $importados++;
        }

        $mensagem = "{$importados} jogador(es) importado(s) com sucesso.";
        if ($erros) {
            $mensagem .= ' Erros: ' . implode(' | ', $erros);
        }

        return redirect()->route('admin.jogadores')->with('success', $mensagem);
    }
}
