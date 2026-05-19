<x-app-layout>
    {{-- Flash messages --}}
    @if (session('success'))
        <div class="border-b border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400 text-center">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="border-b border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400 text-center">
            {{ session('error') }}
        </div>
    @endif
    @if (session('info'))
        <div class="border-b border-sky-500/20 bg-sky-500/10 px-4 py-3 text-sm text-sky-400 text-center">
            {{ session('info') }}
        </div>
    @endif

    {{-- Hero da liga --}}
    <div class="relative overflow-hidden border-b border-slate-800 bg-slate-900">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(16,185,129,0.08),transparent_60%)]"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <a href="{{ route('leagues.index') }}" class="text-sm text-slate-500 hover:text-slate-300 transition">Minhas Ligas</a>
                        <span class="text-slate-700">/</span>
                        <span class="text-sm text-slate-400">{{ $league->name }}</span>
                    </div>
                    <h1 class="text-2xl font-extrabold text-white sm:text-3xl">{{ $league->name }}</h1>

                    @if ($championship)
                        <p class="mt-1 text-slate-400">{{ $championship->name }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        {{-- Status --}}
                        @if ($league->isWaiting())
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                Aguardando times
                            </span>
                        @elseif ($league->isInProgress())
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Em andamento
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-500/30 bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-400">
                                Encerrada
                            </span>
                        @endif

                        {{-- Visibilidade --}}
                        @if ($league->type === 'public')
                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-600 bg-slate-800 px-3 py-1 text-xs text-slate-400">
                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3" /></svg>
                                Pública
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 text-xs text-sky-400">
                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                Privada
                            </span>
                        @endif

                        {{-- Dono --}}
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-600 bg-slate-800 px-3 py-1 text-xs text-slate-400">
                            Criada por <strong class="text-white ml-1">{{ $league->owner->name }}</strong>
                        </span>
                    </div>
                </div>

                {{-- Ações principais --}}
                <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                    @if ($canJoin)
                        <a href="{{ route('leagues.teams.create', $league) }}"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9" /></svg>
                            Entrar com meu time
                        </a>
                    @endif

                    @if ($canStart)
                        <form action="{{ route('leagues.start', $league) }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-violet-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-violet-400 active:scale-95"
                                onclick="return confirm('Iniciar a liga agora? Novos times não poderão mais entrar.')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
                                Iniciar Liga
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Times inscritos --}}
            <div class="lg:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-widest text-slate-400">
                        Times Inscritos
                        <span class="ml-1 text-white">{{ $league->teams->count() }}/{{ $league->max_teams }}</span>
                    </h2>
                </div>

                {{-- Grid de times --}}
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($league->teams as $lt)
                        <div class="flex items-center gap-4 rounded-2xl border border-slate-700 bg-slate-900 p-4
                            {{ $lt->user_id === auth()->id() ? 'border-emerald-500/40 ring-1 ring-emerald-500/20' : '' }}">
                            {{-- Badge --}}
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-2xl overflow-hidden">
                                @if ($lt->team?->badge)
                                    <img src="{{ asset('storage/' . $lt->team->badge) }}" alt="{{ $lt->name }}" class="h-full w-full object-contain p-1" />
                                @else
                                    <span class="text-slate-500 font-bold text-sm">{{ strtoupper(substr($lt->name, 0, 2)) }}</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-white truncate">{{ $lt->name }}</p>
                                @if ($lt->user)
                                    <p class="text-xs text-slate-400 truncate">{{ $lt->user->name }}</p>
                                @else
                                    <p class="text-xs text-slate-500 italic">Controlado pela IA</p>
                                @endif
                                @if ($lt->coach)
                                    <p class="text-xs text-emerald-400/80 truncate mt-0.5">🎽 {{ $lt->coach->name }}</p>
                                @endif
                            </div>
                            @if ($lt->user_id === auth()->id())
                                <span class="shrink-0 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-400 border border-emerald-500/20">Você</span>
                            @endif
                        </div>
                    @endforeach

                    {{-- Vagas restantes --}}
                    @for ($i = $league->teams->count(); $i < $league->max_teams; $i++)
                        <div class="flex items-center gap-4 rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 p-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-dashed border-slate-700 text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9" /></svg>
                            </div>
                            <div>
                                <p class="text-sm text-slate-600">Vaga disponível</p>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Sidebar com informações --}}
            <div class="space-y-4">

                {{-- Código de convite --}}
                @if ($league->invite_code && ($isOwner || $userTeam))
                    <div class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5" x-data="{ copied: false }">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-sky-400">Código de Convite</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 rounded-lg bg-slate-800 px-4 py-2.5 text-center text-lg font-bold tracking-[0.3em] text-white border border-slate-700">
                                {{ $league->invite_code }}
                            </code>
                            <button @click="navigator.clipboard.writeText('{{ $league->invite_code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="shrink-0 rounded-lg border border-slate-700 bg-slate-800 p-2.5 text-slate-400 hover:text-white transition">
                                <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                                <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-sky-400/70">Compartilhe este código para convidar jogadores.</p>
                    </div>
                @endif

                {{-- Detalhes do campeonato --}}
                @if ($championship)
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Campeonato</p>
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Formato</span>
                                <span class="font-medium text-white">
                                    {{ \App\Models\Championship::$types[$championship->type] ?? $championship->type }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Jogos</span>
                                <span class="font-medium text-white">
                                    {{ \App\Models\Championship::$legs[$championship->legs] ?? $championship->legs }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Times</span>
                                @if ($league->usesRandomAssignment())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-500/15 px-2 py-0.5 text-xs font-medium text-violet-400">
                                        🎲 Sorteio
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-400">
                                        🏟️ Escolha livre
                                    </span>
                                @endif
                            </div>
                            @if ($championship->promotion_spots)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-400">Promoção</span>
                                    <span class="font-medium text-emerald-400">↑ {{ $championship->promotion_spots }} times</span>
                                </div>
                            @endif
                            @if ($championship->relegation_spots)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-400">Rebaixamento</span>
                                    <span class="font-medium text-red-400">↓ {{ $championship->relegation_spots }} times</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Progresso de inscrições --}}
                @if ($league->isWaiting())
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Inscrições</p>
                        @php $pct = $league->teams->count() / max($league->max_teams, 1) * 100 @endphp
                        <div class="mb-2 flex items-end justify-between">
                            <span class="text-2xl font-extrabold text-white">{{ $league->teams->count() }}</span>
                            <span class="text-sm text-slate-500">de {{ $league->max_teams }} times</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-800">
                            <div class="h-2 rounded-full bg-emerald-500 transition-all" style="width: {{ $pct }}%"></div>
                        </div>
                        @php $faltam = $league->max_teams - $league->teams->count() @endphp
                        <p class="mt-2 text-xs text-slate-500">
                            @if ($faltam > 0)
                                Faltam {{ $faltam }} {{ Str::plural('time', $faltam) }} para completar.
                            @else
                                Liga com todas as vagas preenchidas.
                            @endif
                        </p>
                        @if ($isOwner && !$canStart)
                            <p class="mt-1 text-xs text-amber-400/80">Mínimo de 2 times para iniciar.</p>
                        @endif
                    </div>
                @endif

                @if ($league->isInProgress())
                    <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-emerald-400">Em Andamento</p>
                        <p class="text-sm text-slate-400">
                            Iniciada em {{ $league->started_at?->format('d/m/Y') }}.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
