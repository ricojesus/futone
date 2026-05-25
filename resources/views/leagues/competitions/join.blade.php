<x-app-layout>
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 mb-1 text-sm">
                <a href="{{ route('leagues.show', $league) }}" class="text-slate-500 hover:text-slate-300 transition">{{ $league->name }}</a>
                <span class="text-slate-700">/</span>
                <a href="{{ route('competitions.show', [$league, $competition]) }}" class="text-slate-500 hover:text-slate-300 transition">{{ $competition->name }}</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400">Escolher Time</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white">Escolha seu Time</h1>
            <p class="mt-1 text-sm text-slate-400">
                Selecione o time que irá representar você em <strong class="text-white">{{ $competition->name }}</strong>.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-400">
                <ul class="space-y-1 list-disc list-inside">
                    @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('competitions.join.store', [$league, $competition]) }}" method="POST" class="space-y-6"
              x-data="{ selectedTeam: '{{ old('team_id') }}' }">
            @csrf

            {{-- Seleção de time --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Time</h2>

                @if ($teams->isEmpty())
                    <p class="text-sm text-slate-500 italic">Todos os times disponíveis já estão inscritos nesta competição.</p>
                @else
                    <input type="hidden" name="team_id" :value="selectedTeam" />
                    <div class="grid gap-3 sm:grid-cols-2 max-h-96 overflow-y-auto pr-1">
                        @foreach ($teams as $team)
                            <button type="button"
                                @click="selectedTeam = '{{ $team->id }}'"
                                :class="selectedTeam === '{{ $team->id }}'
                                    ? 'border-emerald-500 bg-emerald-500/10 ring-1 ring-emerald-500/30'
                                    : 'border-slate-700 bg-slate-800/50 hover:border-slate-600'"
                                class="flex items-center gap-3 rounded-xl border p-3 text-left transition w-full">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-800 overflow-hidden border border-slate-700">
                                    @if ($team->badge ?? false)
                                        <img src="{{ asset('storage/' . $team->badge) }}" alt="{{ $team->name }}" class="h-full w-full object-contain p-1" />
                                    @else
                                        <span class="text-xs font-bold text-slate-400">{{ strtoupper(substr($team->name, 0, 2)) }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white truncate">{{ $team->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $team->state?->code ?? '—' }}
                                        · {{ number_format($team->fans_base / 1_000_000, 1) }}M torcedores
                                    </p>
                                </div>
                                <div :class="selectedTeam === '{{ $team->id }}' ? 'opacity-100' : 'opacity-0'"
                                     class="shrink-0 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Ações --}}
            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('competitions.show', [$league, $competition]) }}"
                    class="rounded-xl border border-slate-600 px-5 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800">
                    Cancelar
                </a>
                <button type="submit"
                    :disabled="!selectedTeam"
                    :class="selectedTeam ? 'bg-emerald-500 hover:bg-emerald-400 cursor-pointer' : 'bg-slate-700 cursor-not-allowed text-slate-500'"
                    class="rounded-xl px-6 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition active:scale-95">
                    Confirmar Inscrição
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
