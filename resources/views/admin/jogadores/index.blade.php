<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">

        {{-- Cabeçalho --}}
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração</p>
                <h1 class="text-2xl font-extrabold text-white">Jogadores</h1>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('admin.jogadores.create') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Novo jogador
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif

        {{-- Upload CSV --}}
        <div class="mb-6 rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h2 class="mb-3 text-sm font-semibold text-slate-300">Importar via CSV</h2>
            <p class="mb-4 text-xs text-slate-500">
                O arquivo deve conter as colunas: <code class="text-emerald-400">nome, posicao, nacionalidade, idade, forca</code>.
                A posição deve ser: <code class="text-emerald-400">goleiro</code>, <code class="text-emerald-400">defesa</code>, <code class="text-emerald-400">meio</code> ou <code class="text-emerald-400">ataque</code>.
            </p>
            <form method="POST" action="{{ route('admin.jogadores.upload') }}" enctype="multipart/form-data"
                class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-medium text-slate-400">Arquivo CSV</label>
                    <input type="file" name="arquivo" accept=".csv,.txt" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300
                               file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-1 file:text-xs file:text-white file:cursor-pointer" />
                    @error('arquivo') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-600 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-slate-300 transition hover:bg-slate-800 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Importar
                </button>
            </form>
        </div>

        {{-- Tabela --}}
        <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
            @if($jogadores->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" />
                    </svg>
                    <p class="text-sm">Nenhum jogador cadastrado ainda.</p>
                    <a href="{{ route('admin.jogadores.create') }}" class="mt-3 text-sm text-emerald-400 hover:underline">Criar o primeiro jogador</a>
                </div>
            @else
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-800/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Jogador</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Posição</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Nacionalidade</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Idade</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Força</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($jogadores as $jogador)
                            <tr class="transition hover:bg-slate-800/40">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($jogador->foto)
                                            <img src="{{ Storage::url($jogador->foto) }}" class="h-9 w-9 rounded-full object-cover ring-2 ring-slate-700" />
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-700 text-sm font-bold text-slate-300">
                                                {{ substr($jogador->nome, 0, 1) }}
                                            </span>
                                        @endif
                                        <span class="text-sm font-semibold text-white">{{ $jogador->nome }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium
                                        @switch($jogador->posicao)
                                            @case('goleiro') bg-yellow-500/10 text-yellow-400 @break
                                            @case('defesa')  bg-sky-500/10 text-sky-400 @break
                                            @case('meio')    bg-violet-500/10 text-violet-400 @break
                                            @case('ataque')  bg-red-500/10 text-red-400 @break
                                        @endswitch">
                                        {{ ucfirst($jogador->posicao) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-400">{{ $jogador->nacionalidade ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-400">{{ $jogador->idade ?? '—' }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg font-bold text-sm
                                        {{ $jogador->forca >= 80 ? 'bg-emerald-500/20 text-emerald-400' : ($jogador->forca >= 65 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-slate-700 text-slate-400') }}">
                                        {{ $jogador->forca }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t border-slate-800 px-6 py-4">
                    {{ $jogadores->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
