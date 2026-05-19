<x-app-layout>
    {{-- Header ──────────────────────────────────────────────────────── --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 mb-1 text-sm">
                <a href="{{ route('dashboard') }}" class="text-slate-500 hover:text-slate-300 transition">Dashboard</a>
                <span class="text-slate-700">/</span>
                <a href="{{ route('leagues.show', $league) }}" class="text-slate-500 hover:text-slate-300 transition">
                    {{ $league->name }}
                </a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400">Entrar na Liga</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white">Entrar na Liga</h1>
            <p class="mt-1 text-sm text-slate-400">
                Esta liga usa <span class="font-semibold text-violet-400">sorteio</span> — seu time será definido aleatoriamente ao confirmar.
            </p>
        </div>
    </div>

    {{-- Corpo ───────────────────────────────────────────────────────── --}}
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">

        {{-- Card de confirmação --}}
        <div class="rounded-2xl border border-violet-500/30 bg-slate-900 overflow-hidden">

            {{-- Ícone de sorteio --}}
            <div class="bg-violet-500/10 px-6 py-8 text-center">
                <div class="text-6xl mb-3" x-data="{ spin: false }" @click="spin = !spin">
                    <span :class="spin ? 'animate-spin inline-block' : 'inline-block'">🎲</span>
                </div>
                <h2 class="text-xl font-bold text-white">Sorteio de Time</h2>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $available->count() }} {{ $available->count() === 1 ? 'time disponível' : 'times disponíveis' }} no pool de sorteio
                </p>
            </div>

            {{-- Times no pool --}}
            <div class="px-6 py-4 border-t border-slate-800">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3">
                    Times que podem ser sorteados
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($available as $team)
                        <span class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-1 text-xs font-medium text-slate-300">
                            {{ $team->name }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Formulário de confirmação --}}
            <div class="px-6 py-5 border-t border-slate-800">
                <form action="{{ route('leagues.teams.store', $league) }}" method="POST">
                    @csrf

                    {{-- Treinador (opcional) --}}
                    @if ($coaches->isNotEmpty())
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">
                                Treinador <span class="text-slate-500 font-normal">(opcional)</span>
                            </label>
                            <select name="coach_id"
                                    class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white
                                           focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
                                <option value="">— Sem treinador por enquanto —</option>
                                @foreach ($coaches as $coach)
                                    <option value="{{ $coach->id }}" {{ old('coach_id') == $coach->id ? 'selected' : '' }}>
                                        {{ $coach->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Aviso --}}
                    <div class="mb-5 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-xs text-amber-400">
                        ⚠️ Após confirmar, seu time será sorteado e a escolha é definitiva. Você não poderá trocar de time dentro desta liga.
                    </div>

                    {{-- Ações --}}
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('leagues.show', $league) }}"
                           class="rounded-xl border border-slate-600 px-5 py-2.5 text-sm font-medium text-slate-300
                                  transition hover:bg-slate-800">
                            Cancelar
                        </a>
                        <button type="submit"
                                class="rounded-xl bg-violet-600 px-6 py-2.5 text-sm font-bold uppercase tracking-wider
                                       text-white transition hover:bg-violet-500 active:scale-95">
                            🎲 Sortear meu time
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </div>
</x-app-layout>
