<x-app-layout>
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-8 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração</p>
                <h1 class="text-2xl font-extrabold text-white">Treinadores</h1>
            </div>
            <a href="{{ route('admin.coaches.create') }}"
                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Novo treinador
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Treinador</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">País</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($coaches as $coach)
                        <tr class="transition hover:bg-slate-800/40">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-700 text-sm font-bold text-slate-300">
                                        {{ substr($coach->name, 0, 1) }}
                                    </span>
                                    <span class="text-sm font-semibold text-white">{{ $coach->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-400">
                                {{ $coach->country?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.coaches.edit', $coach) }}"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-10 text-center text-sm text-slate-500">
                                Nenhum treinador cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="border-t border-slate-800 px-6 py-4">
                {{ $coaches->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
