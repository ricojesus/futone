<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">

        <div class="mb-8 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração</p>
                <h1 class="text-2xl font-extrabold text-white">Times</h1>
            </div>
            <a href="{{ route('admin.teams.create') }}"
                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Novo time
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 px-5 py-4 text-sm text-red-400">
                {{ session('error') }}
            </div>
        @endif

        {{-- ── Upload CSV ───────────────────────────────────────────────── --}}
        <div class="mb-4 rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-300">Importar times via CSV</h2>
            <p class="mb-4 text-xs text-slate-500">
                <a href="{{ route('admin.csv-template', 'times') }}" class="float-right text-xs text-emerald-500 hover:text-emerald-400 underline underline-offset-2">⬇ Baixar template</a>
            Colunas:
                <code class="text-emerald-400">name</code> (obrigatório),
                <code class="text-emerald-400">state</code> (UF ex: SP),
                <code class="text-emerald-400">country_code</code> (ex: BRA),
                <code class="text-emerald-400">overall</code> (1–99),
                <code class="text-emerald-400">state_division</code> (first|second),
                <code class="text-emerald-400">national_division</code> (first|second|vazio),
                <code class="text-emerald-400">tolerance</code>,
                <code class="text-emerald-400">fans_base</code>,
                <code class="text-emerald-400">stadium_capacity</code>.
                O slug é gerado automaticamente a partir do nome.
                Reimportação atualiza o registro existente pelo slug.
            </p>
            <form method="POST" action="{{ route('admin.teams.upload') }}" enctype="multipart/form-data"
                class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <input type="file" name="file" accept=".csv,.txt" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300
                               file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-1 file:text-xs file:text-white file:cursor-pointer">
                </div>
                <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-600 px-5 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-slate-800 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Importar CSV
                </button>
            </form>
        </div>

        {{-- ── Upload Logos ZIP ─────────────────────────────────────────── --}}
        <div class="mb-6 rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-300">Importar logos via ZIP</h2>
            <p class="mb-4 text-xs text-slate-500">
                Arquivo <code class="text-emerald-400">.zip</code> contendo as imagens dos escudos.
                Cada arquivo deve ser nomeado com o <strong class="text-slate-400">slug do time</strong>
                (ex: <code class="text-emerald-400">corinthians.png</code>, <code class="text-emerald-400">sao-paulo.webp</code>).
                Formatos aceitos: <code class="text-emerald-400">png, jpg, jpeg, webp, svg</code>. Tamanho máximo: 50 MB.
            </p>
            <form method="POST" action="{{ route('admin.teams.upload-logos') }}" enctype="multipart/form-data"
                class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <input type="file" name="file" accept=".zip" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300
                               file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-1 file:text-xs file:text-white file:cursor-pointer">
                </div>
                <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-600 px-5 py-2.5 text-sm font-semibold text-amber-400 transition hover:bg-amber-500/10 hover:text-amber-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                    Importar logos
                </button>
            </form>
        </div>

        {{-- ── Tabela ───────────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Estado</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Overall</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Estadual</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Nacional</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($teams as $team)
                        <tr class="transition hover:bg-slate-800/40">
                            {{-- Logo + nome --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if($team->badge)
                                        <img src="{{ Storage::url($team->badge) }}" alt="{{ $team->name }}"
                                            class="h-8 w-8 rounded object-contain bg-slate-800 p-0.5">
                                    @else
                                        <span class="flex h-8 w-8 items-center justify-center rounded bg-slate-700 text-xs font-bold text-slate-300">
                                            {{ strtoupper(substr($team->name, 0, 2)) }}
                                        </span>
                                    @endif
                                    <div>
                                        <p class="font-semibold text-white">{{ $team->name }}</p>
                                        @if($team->slug)
                                            <p class="text-[10px] text-slate-600">{{ $team->slug }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Estado --}}
                            <td class="px-4 py-3 text-slate-400">
                                {{ $team->state?->code ?? '—' }}
                            </td>

                            {{-- Overall --}}
                            <td class="px-4 py-3 text-center">
                                <span class="font-bold tabular-nums
                                    @if(($team->overall ?? 0) >= 80) text-emerald-400
                                    @elseif(($team->overall ?? 0) >= 65) text-amber-400
                                    @else text-slate-400
                                    @endif">
                                    {{ $team->overall ?? '—' }}
                                </span>
                            </td>

                            {{-- Divisão estadual --}}
                            <td class="px-4 py-3 text-center">
                                @if($team->state_division === 'first')
                                    <span class="rounded-full border border-blue-500/40 bg-blue-500/10 px-2 py-0.5 text-xs font-semibold text-blue-400">A1</span>
                                @elseif($team->state_division === 'second')
                                    <span class="rounded-full border border-slate-600 bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-400">A2</span>
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>

                            {{-- Divisão nacional --}}
                            <td class="px-4 py-3 text-center">
                                @if($team->national_division === 'first')
                                    <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-400">Série A</span>
                                @elseif($team->national_division === 'second')
                                    <span class="rounded-full border border-slate-600 bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-400">Série B</span>
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>

                            {{-- Ações --}}
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.teams.edit', $team) }}"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">
                                Nenhum time cadastrado. Importe um CSV para começar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="border-t border-slate-800 px-6 py-4">
                {{ $teams->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
