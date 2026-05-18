<x-app-layout>
    {{-- Header --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold text-white">Minhas Ligas</h1>
                    <p class="mt-1 text-sm text-slate-400">Ligas que você criou ou participa.</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('leagues.join') }}"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-600 px-4 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3M13.5 19.5l-.397-1.191A2.25 2.25 0 0 0 11.963 17H7.5A2.25 2.25 0 0 1 5.25 14.75v-8A2.25 2.25 0 0 1 7.5 4.5h4.463a2.25 2.25 0 0 1 1.14.308L15 6" /></svg>
                        Entrar numa Liga
                    </a>
                    <a href="{{ route('leagues.create') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        Nova Liga
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if ($leagues->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-8 py-20 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" /></svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold text-white">Nenhuma liga ainda</h3>
                <p class="mb-6 text-sm text-slate-400">Crie sua própria liga ou entre em uma existente com um código de convite.</p>
                <div class="flex gap-3">
                    <a href="{{ route('leagues.join') }}"
                        class="rounded-xl border border-slate-600 px-5 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800">
                        Entrar numa Liga
                    </a>
                    <a href="{{ route('leagues.create') }}"
                        class="rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-400">
                        Criar Liga
                    </a>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($leagues as $league)
                    @php
                        $championship = $league->championships->first();
                        $userTeam = $league->teams->firstWhere('user_id', auth()->id());
                        $isOwner = $league->owner_id === auth()->id();
                    @endphp
                    <a href="{{ route('leagues.show', $league) }}"
                        class="group flex flex-col rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-emerald-500/40 hover:bg-slate-800/60">

                        <div class="mb-3 flex items-start justify-between gap-2">
                            <h3 class="font-bold text-white leading-snug">{{ $league->name }}</h3>
                            {{-- Status badge --}}
                            @if ($league->isWaiting())
                                <span class="shrink-0 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-400">Aguardando</span>
                            @elseif ($league->isInProgress())
                                <span class="shrink-0 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-400">Em andamento</span>
                            @else
                                <span class="shrink-0 rounded-full border border-slate-600 bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-400">Encerrada</span>
                            @endif
                        </div>

                        @if ($championship)
                            <p class="mb-3 text-sm text-slate-400">{{ $championship->name }}</p>
                        @endif

                        <div class="mt-auto flex items-center justify-between border-t border-slate-800 pt-3">
                            <span class="text-xs text-slate-500">
                                {{ $league->teams->count() }}/{{ $league->max_teams }} times
                            </span>
                            <div class="flex gap-1.5">
                                @if ($isOwner)
                                    <span class="rounded-full bg-violet-500/10 border border-violet-500/20 px-2 py-0.5 text-xs text-violet-400">Criador</span>
                                @endif
                                @if ($userTeam)
                                    <span class="rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 text-xs text-emerald-400">Inscrito</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
