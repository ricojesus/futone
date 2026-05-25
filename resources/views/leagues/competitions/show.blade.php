<x-app-layout>
    @if (session('success'))
        <div class="border-b border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400 text-center">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="border-b border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400 text-center">{{ session('error') }}</div>
    @endif
    @if (session('info'))
        <div class="border-b border-sky-500/20 bg-sky-500/5 px-4 py-3 text-sm text-sky-400 text-center">{{ session('info') }}</div>
    @endif

    {{-- Header --}}
    <div class="relative overflow-hidden border-b border-slate-800 bg-slate-900">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(16,185,129,0.07),transparent_60%)]"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

            {{-- Breadcrumb --}}
            <div class="mb-3 flex items-center gap-2 text-sm">
                <a href="{{ route('leagues.index') }}" class="text-slate-500 hover:text-slate-300 transition">Minhas Ligas</a>
                <span class="text-slate-700">/</span>
                <a href="{{ route('leagues.show', $league) }}" class="text-slate-500 hover:text-slate-300 transition">{{ $league->name }}</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400">{{ $competition->name }}</span>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold text-white sm:text-3xl">{{ $competition->name }}</h1>
                    <div class="mt-2 flex flex-wrap gap-2">
                        {{-- Status --}}
                        @if ($competition->isWaiting())
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>Aguardando
                            </span>
                        @elseif ($competition->isInProgress())
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>Rodada {{ $competition->current_round }}/{{ $competition->total_rounds }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-600 bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-400">Encerrada</span>
                        @endif

                        <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs text-slate-400">
                            {{ $competition->divisionLabel() }}
                        </span>
                        <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs text-slate-400">
                            {{ $competition->teams_count }} times · {{ $competition->total_rounds }} rodadas
                        </span>
                    </div>
                </div>

                {{-- Ação principal --}}
                <div class="shrink-0">
                    @if ($myTeam)
                        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                            Você gerencia <strong class="text-white">{{ $myTeam->name }}</strong>
                        </div>
                    @elseif ($competition->isWaiting())
                        <a href="{{ route('competitions.join', [$league, $competition]) }}"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3M13.5 19.5l-.397-1.191A2.25 2.25 0 0 0 11.963 17H7.5A2.25 2.25 0 0 1 5.25 14.75v-8A2.25 2.25 0 0 1 7.5 4.5h4.463a2.25 2.25 0 0 1 1.14.308L15 6" />
                            </svg>
                            Escolher time
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-3">

            {{-- Coluna principal: Agenda --}}
            <div class="lg:col-span-2">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Agenda de Partidas</h2>

                @if ($matchesByRound->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-8 py-12 text-center">
                        <p class="text-slate-500">Nenhuma partida gerada ainda.</p>
                    </div>
                @else
                    <div class="space-y-6">
                        @foreach ($matchesByRound as $round => $matches)
                            <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                                {{-- Cabeçalho da rodada --}}
                                <div class="flex items-center justify-between border-b border-slate-800 px-5 py-3">
                                    <span class="text-sm font-semibold text-white">Rodada {{ $round }}</span>
                                    @php $leg = $matches->first()->leg ?? 1 @endphp
                                    <span class="text-xs text-slate-500">{{ $leg === 1 ? 'Turno' : 'Returno' }}</span>
                                </div>

                                {{-- Partidas da rodada --}}
                                <div class="divide-y divide-slate-800">
                                    @foreach ($matches as $match)
                                        @php
                                            $played  = $match->status === 'finished';
                                            $isMyMatch = $myTeam && in_array($myTeam->id, [$match->home_team_id, $match->away_team_id]);
                                        @endphp
                                        <div class="flex items-center gap-3 px-5 py-3 {{ $isMyMatch ? 'bg-emerald-500/5' : '' }}">
                                            {{-- Time mandante --}}
                                            <div class="flex-1 text-right">
                                                <span class="text-sm font-medium {{ $isMyMatch && $myTeam?->id === $match->home_team_id ? 'text-emerald-400' : 'text-white' }}">
                                                    {{ $match->homeTeam?->name ?? '—' }}
                                                </span>
                                            </div>

                                            {{-- Placar --}}
                                            <div class="shrink-0 w-20 text-center">
                                                @if ($played)
                                                    <span class="text-base font-bold tabular-nums text-white">
                                                        {{ $match->home_score }} × {{ $match->away_score }}
                                                    </span>
                                                @else
                                                    <span class="text-xs font-semibold text-slate-500 tracking-widest">VS</span>
                                                @endif
                                            </div>

                                            {{-- Time visitante --}}
                                            <div class="flex-1 text-left">
                                                <span class="text-sm font-medium {{ $isMyMatch && $myTeam?->id === $match->away_team_id ? 'text-emerald-400' : 'text-white' }}">
                                                    {{ $match->awayTeam?->name ?? '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Coluna lateral: Classificação --}}
            <div>
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Classificação</h2>

                @if ($standings->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-6 py-8 text-center">
                        <p class="text-slate-500 text-sm">Sem times inscritos.</p>
                    </div>
                @else
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-800 text-xs text-slate-500">
                                    <th class="px-4 py-3 text-left w-6">#</th>
                                    <th class="px-4 py-3 text-left">Time</th>
                                    <th class="px-2 py-3 text-center" title="Jogos">J</th>
                                    <th class="px-2 py-3 text-center" title="Vitórias">V</th>
                                    <th class="px-2 py-3 text-center" title="Empates">E</th>
                                    <th class="px-2 py-3 text-center" title="Derrotas">D</th>
                                    <th class="px-2 py-3 text-center font-bold text-white" title="Pontos">P</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @foreach ($standings as $pos => $team)
                                    @php
                                        $isMe = $myTeam && $team->id === $myTeam->id;
                                        $jogos = $team->wins + $team->draws + $team->losses;
                                    @endphp
                                    <tr class="{{ $isMe ? 'bg-emerald-500/5' : 'hover:bg-slate-800/50' }} transition">
                                        <td class="px-4 py-2.5 text-slate-500 text-xs tabular-nums">{{ $pos + 1 }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="font-medium {{ $isMe ? 'text-emerald-400' : 'text-white' }}">
                                                {{ $team->name }}
                                            </span>
                                            @if (! $team->user_id)
                                                <span class="ml-1 text-xs text-slate-600">CPU</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $jogos }}</td>
                                        <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $team->wins }}</td>
                                        <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $team->draws }}</td>
                                        <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $team->losses }}</td>
                                        <td class="px-2 py-2.5 text-center font-bold text-white tabular-nums">{{ $team->points }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
