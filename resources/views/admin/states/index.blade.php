<x-app-layout>
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">

        <div class="mb-8 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração</p>
                <h1 class="text-2xl font-extrabold text-white">Estados</h1>
            </div>
            <a href="{{ route('admin.states.create') }}"
                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Novo estado
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-400">
                {{ session('error') }}
            </div>
        @endif

        {{-- Import CSV --}}
        <div class="mb-6 rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-300">Importar via planilha</h2>
            <p class="mb-4 text-xs text-slate-500">
                Colunas: <code class="text-emerald-400">name</code>, <code class="text-emerald-400">code</code> (obrigatórios),
                <code class="text-emerald-400">country_code</code> (ex: BRA). Duplicatas por código + país são ignoradas.
            </p>
            <form method="POST" action="{{ route('admin.states.upload') }}" enctype="multipart/form-data"
                class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <input type="file" name="file" accept=".csv,.txt" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300
                               file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-1 file:text-xs file:text-white file:cursor-pointer">
                    @error('file')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-600 px-5 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-slate-800 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Importar
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Sigla</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">País</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($states as $state)
                        <tr class="transition hover:bg-slate-800/40">
                            <td class="px-6 py-4 text-sm font-semibold text-white">{{ $state->name }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-md bg-slate-700 px-2 py-0.5 text-xs font-mono font-semibold text-slate-300">
                                    {{ $state->code }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-400">
                                {{ $state->country?->flag }} {{ $state->country?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.states.edit', $state) }}"
                                    class="inline-flex items-center rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">
                                Nenhum estado cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-800 px-6 py-4">
                {{ $states->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
