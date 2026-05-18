<x-app-layout>
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

        {{-- Cabeçalho --}}
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração</p>
                <h1 class="text-2xl font-extrabold text-white">Países</h1>
            </div>
            <a href="{{ route('admin.countries.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Novo país
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
            @if($countries->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3" />
                    </svg>
                    <p class="text-sm">Nenhum país cadastrado ainda.</p>
                    <a href="{{ route('admin.countries.create') }}" class="mt-3 text-sm text-emerald-400 hover:underline">Criar o primeiro país</a>
                </div>
            @else
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-800/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">País</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Código</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Jogadores</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($countries as $country)
                            <tr class="transition hover:bg-slate-800/40">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl">{{ $country->flag ?? '🏳️' }}</span>
                                        <span class="text-sm font-semibold text-white">{{ $country->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="rounded-md bg-slate-800 px-2 py-1 font-mono text-xs text-slate-300">
                                        {{ $country->code }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-slate-400">
                                    {{ $country->players_count }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.countries.edit', $country) }}"
                                            class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-400 transition hover:bg-slate-800 hover:text-white">
                                            Editar
                                        </a>
                                        @if($country->players_count === 0)
                                            <form method="POST" action="{{ route('admin.countries.destroy', $country) }}"
                                                onsubmit="return confirm('Remover {{ $country->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="rounded-lg border border-red-500/30 px-3 py-1.5 text-xs font-medium text-red-400 transition hover:bg-red-500/10">
                                                    Remover
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t border-slate-800 px-6 py-4">
                    {{ $countries->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
