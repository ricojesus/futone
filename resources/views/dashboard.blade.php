<x-app-layout>
    {{-- Hero de boas-vindas --}}
    <div class="relative overflow-hidden border-b border-slate-800 bg-slate-900">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(16,185,129,0.12),transparent_60%)]"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Bem-vindo de volta</p>
            <h1 class="mt-1 text-3xl font-extrabold text-white sm:text-4xl">
                {{ Auth::user()->name }} <span class="text-slate-500 font-normal text-2xl">— pronto para jogar?</span>
            </h1>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 space-y-12">

        {{-- Ações principais --}}
        <div>
            <h2 class="mb-6 text-xs font-semibold uppercase tracking-widest text-slate-500">O que deseja fazer?</h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">

                {{-- Criar uma liga --}}
                <div class="group relative flex flex-col rounded-2xl border border-slate-700 bg-slate-900 p-6 transition hover:border-emerald-500/60 hover:bg-slate-800/60">
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-white">Criar uma Liga</h3>
                    <p class="mb-6 flex-1 text-sm leading-relaxed text-slate-400">
                        Monte sua própria liga, convide amigos e defina as regras. Você controla o campeonato.
                    </p>
                    <a href="{{ route('leagues.create') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                        Criar liga
                    </a>
                </div>

                {{-- Entrar numa liga --}}
                <div class="group relative flex flex-col rounded-2xl border border-slate-700 bg-slate-900 p-6 transition hover:border-sky-500/60 hover:bg-slate-800/60">
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-sky-500/10 text-sky-400 ring-1 ring-sky-500/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3M13.5 19.5l-.397-1.191A2.25 2.25 0 0 0 11.963 17H7.5A2.25 2.25 0 0 1 5.25 14.75v-8A2.25 2.25 0 0 1 7.5 4.5h4.463a2.25 2.25 0 0 1 1.14.308L15 6m0 0v4.5m0-4.5h3.75" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-white">Entrar numa Liga</h3>
                    <p class="mb-6 flex-1 text-sm leading-relaxed text-slate-400">
                        Encontre uma liga pública ou insira o código de convite para disputar com outros jogadores.
                    </p>
                    <a href="{{ route('leagues.join') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-sky-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-sky-400 active:scale-95">
                        Entrar
                    </a>
                </div>

                {{-- Continuar jogando / Minhas Ligas --}}
                @php
                    $activeLeague = $myLeagues->firstWhere('status', 'in_progress');
                @endphp
                <div class="group relative flex flex-col rounded-2xl border border-slate-700 bg-slate-900 p-6 transition
                    {{ $activeLeague ? 'hover:border-violet-500/60 hover:bg-slate-800/60' : '' }}
                    sm:col-span-2 lg:col-span-1">
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-violet-500/10 text-violet-400 ring-1 ring-violet-500/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-white">Continuar Jogando</h3>
                    @if ($activeLeague)
                        <p class="mb-6 flex-1 text-sm leading-relaxed text-slate-400">
                            <strong class="text-white">{{ $activeLeague->name }}</strong> está em andamento.
                        </p>
                        <a href="{{ route('leagues.show', $activeLeague) }}"
                            class="inline-flex items-center justify-center rounded-xl bg-violet-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-violet-400 active:scale-95">
                            Jogar agora
                        </a>
                    @else
                        <p class="mb-6 flex-1 text-sm leading-relaxed text-slate-400">
                            Você ainda não está em nenhuma liga ativa. Crie ou entre em uma liga para começar a jogar.
                        </p>
                        <button disabled
                            class="inline-flex cursor-not-allowed items-center justify-center rounded-xl bg-slate-700 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-slate-500">
                            Sem liga ativa
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Minhas ligas --}}
        @if ($myLeagues->isNotEmpty())
            <div>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-slate-500">Minhas Ligas</h2>
                    <a href="{{ route('leagues.index') }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition">Ver todas →</a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($myLeagues->take(6) as $league)
                        @php
                            $championship = $league->championships->first();
                            $isOwner = $league->owner_id === auth()->id();
                        @endphp
                        <a href="{{ route('leagues.show', $league) }}"
                            class="flex items-center gap-4 rounded-2xl border border-slate-700 bg-slate-900 p-4 transition hover:border-emerald-500/30 hover:bg-slate-800/60">
                            {{-- Status dot --}}
                            <div class="shrink-0">
                                @if ($league->isWaiting())
                                    <span class="flex h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-amber-400/20"></span>
                                @elseif ($league->isInProgress())
                                    <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-emerald-400/20 animate-pulse"></span>
                                @else
                                    <span class="flex h-2.5 w-2.5 rounded-full bg-slate-600"></span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-white truncate">{{ $league->name }}</p>
                                <p class="text-xs text-slate-500 truncate">
                                    {{ $championship?->name ?? '—' }}
                                    · {{ $league->teams->count() }}/{{ $league->max_teams }} times
                                </p>
                            </div>
                            @if ($isOwner)
                                <span class="shrink-0 text-xs text-violet-400">Criador</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
