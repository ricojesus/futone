@php
    $positionLabel = [
        'goalkeeper' => 'GK',
        'defender'   => 'ZA',
        'midfielder' => 'ME',
        'forward'    => 'AT',
    ];

    $isHuman   = $leagueTeam->user_id !== null;
    $managerName = $isHuman ? $leagueTeam->user->name : ($leagueTeam->coach?->name ?? 'CPU');
@endphp

<x-app-layout>

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">

            {{-- Breadcrumb --}}
            <div class="flex items-center gap-2 mb-4 text-xs text-slate-500">
                <a href="{{ route('leagues.show', $league) }}" class="hover:text-slate-300 transition">{{ $league->name }}</a>
                <span>/</span>
                <span class="text-slate-400">{{ $leagueTeam->name }}</span>
            </div>

            <div class="flex items-center gap-5">
                <x-team-badge :team="$leagueTeam" size="xl" />
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-extrabold text-white">{{ $leagueTeam->name }}</h1>
                        @if ($isMyTeam)
                            <span class="rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-400">Seu time</span>
                        @endif
                        @if (! $isHuman)
                            <span class="rounded-full bg-slate-800 border border-slate-700 px-2.5 py-0.5 text-xs font-semibold text-slate-500">CPU</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-400">
                        @if ($isHuman)
                            <span class="text-slate-500">Técnico:</span> {{ $managerName }}
                        @else
                            <span class="text-slate-500">Técnico CPU:</span> {{ $managerName }}
                        @endif
                        @if ($lineup)
                            <span class="text-slate-700 mx-2">·</span>
                            <span class="text-slate-500">Formação:</span> {{ $lineup->formation }}
                        @endif
                    </p>
                </div>

                {{-- Botão de escalação própria --}}
                @if ($isMyTeam && $myLeagueTeam)
                    <a href="{{ route('leagues.lineup.edit', [$league, $leagueTeam]) }}"
                       class="shrink-0 inline-flex items-center gap-2 rounded-xl border border-violet-500/40 bg-violet-500/10 px-4 py-2.5 text-sm font-semibold text-violet-400 hover:bg-violet-500/20 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                        Editar Escalação
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">

        {{-- ── Stats nas competições ───────────────────────────────────── --}}
        @if ($competitionTeams->isNotEmpty())
            <div>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">Desempenho nas Competições</h2>
                <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-800 text-left text-xs text-slate-500 uppercase tracking-wide">
                                <th class="px-4 py-2.5 font-medium">Competição</th>
                                <th class="px-3 py-2.5 text-center font-medium">J</th>
                                <th class="px-3 py-2.5 text-center font-medium">V</th>
                                <th class="px-3 py-2.5 text-center font-medium">E</th>
                                <th class="px-3 py-2.5 text-center font-medium">D</th>
                                <th class="px-3 py-2.5 text-center font-medium">GP</th>
                                <th class="px-3 py-2.5 text-center font-medium">GC</th>
                                <th class="px-3 py-2.5 text-center font-medium font-bold text-white">Pts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($competitionTeams as $ct)
                                @php $jogos = $ct->wins + $ct->draws + $ct->losses; @endphp
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-white text-sm">{{ $ct->competition->name }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-center text-slate-400 tabular-nums">{{ $jogos }}</td>
                                    <td class="px-3 py-3 text-center text-slate-400 tabular-nums">{{ $ct->wins }}</td>
                                    <td class="px-3 py-3 text-center text-slate-400 tabular-nums">{{ $ct->draws }}</td>
                                    <td class="px-3 py-3 text-center text-slate-400 tabular-nums">{{ $ct->losses }}</td>
                                    <td class="px-3 py-3 text-center text-slate-400 tabular-nums">{{ $ct->goals_for }}</td>
                                    <td class="px-3 py-3 text-center text-slate-400 tabular-nums">{{ $ct->goals_against }}</td>
                                    <td class="px-3 py-3 text-center font-bold text-white tabular-nums">{{ $ct->points }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ── Escalação titular ───────────────────────────────────────── --}}
        <div>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">
                Escalação Titular
                @if ($lineup)
                    <span class="ml-2 normal-case font-normal text-slate-600">{{ $lineup->formation }}</span>
                @endif
            </h2>

            @if ($starters->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-800 px-6 py-10 text-center">
                    <p class="text-slate-600 text-sm">Nenhuma escalação definida.</p>
                </div>
            @else
                <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                    <div class="divide-y divide-slate-800/60">
                        @foreach ($starters as $player)
                            @php
                                $ovr      = (int) ($player->strength ?? 0);
                                $fit      = (int) ($player->fitness  ?? 100);
                                $ovrColor = match(true) {
                                    $ovr >= 80 => 'text-amber-300',
                                    $ovr >= 65 => 'text-emerald-400',
                                    $ovr >= 50 => 'text-slate-300',
                                    default    => 'text-slate-500',
                                };
                                $fitColor = match(true) {
                                    $fit >= 80 => '#10b981',
                                    $fit >= 60 => '#f59e0b',
                                    $fit >= 40 => '#f97316',
                                    default    => '#ef4444',
                                };
                                $fitLabel = match(true) {
                                    $fit >= 80 => 'text-emerald-400',
                                    $fit >= 60 => 'text-amber-400',
                                    $fit >= 40 => 'text-orange-400',
                                    default    => 'text-red-400',
                                };
                            @endphp
                            <div class="flex items-center gap-3 px-5 py-3">
                                <span class="shrink-0 w-7 text-center text-[10px] font-bold uppercase text-slate-600 bg-slate-800 rounded px-1 py-0.5">
                                    {{ $positionLabel[$player->position] ?? '—' }}
                                </span>
                                <span class="flex-1 text-sm font-medium text-white">{{ $player->name }}</span>
                                {{-- OVR --}}
                                <div class="shrink-0 flex flex-col items-center w-9">
                                    <span class="text-[9px] font-semibold uppercase tracking-wide text-slate-600 leading-none">OVR</span>
                                    <span class="text-sm font-bold tabular-nums leading-tight {{ $ovrColor }}">{{ $ovr }}</span>
                                </div>
                                {{-- Fitness --}}
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <div class="w-14 h-1.5 rounded-full bg-slate-700/80 overflow-hidden">
                                        <div class="h-full rounded-full" style="width:{{ $fit }}%; background:{{ $fitColor }};"></div>
                                    </div>
                                    <span class="text-[10px] font-medium tabular-nums {{ $fitLabel }} w-7">{{ $fit }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ── Elenco completo ─────────────────────────────────────────── --}}
        <div x-data="{ open: false }">
            <button @click="open = !open"
                class="mb-3 flex w-full items-center justify-between text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-slate-300 transition">
                <span>Elenco Completo <span class="font-normal text-slate-600">({{ $squad->count() }} jogadores)</span></span>
                <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''"
                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <div x-show="open" x-collapse>
                <div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden">
                    <div class="divide-y divide-slate-800/40">
                        @foreach ($squad as $player)
                            @php
                                $ovr      = (int) ($player->strength ?? 0);
                                $fit      = (int) ($player->fitness  ?? 100);
                                $ovrColor = match(true) {
                                    $ovr >= 80 => 'text-amber-300',
                                    $ovr >= 65 => 'text-emerald-400',
                                    $ovr >= 50 => 'text-slate-300',
                                    default    => 'text-slate-500',
                                };
                                $fitColor = match(true) {
                                    $fit >= 80 => '#10b981',
                                    $fit >= 60 => '#f59e0b',
                                    $fit >= 40 => '#f97316',
                                    default    => '#ef4444',
                                };
                                $fitLabel = match(true) {
                                    $fit >= 80 => 'text-emerald-400',
                                    $fit >= 60 => 'text-amber-400',
                                    $fit >= 40 => 'text-orange-400',
                                    default    => 'text-red-400',
                                };
                            @endphp
                            <div class="flex items-center gap-3 px-5 py-2.5">
                                <span class="shrink-0 w-7 text-center text-[10px] font-bold uppercase text-slate-600 bg-slate-800 rounded px-1 py-0.5">
                                    {{ $positionLabel[$player->position] ?? '—' }}
                                </span>
                                <span class="flex-1 text-sm text-white">{{ $player->name }}</span>
                                <div class="shrink-0 flex flex-col items-center w-9">
                                    <span class="text-[9px] font-semibold uppercase tracking-wide text-slate-600 leading-none">OVR</span>
                                    <span class="text-sm font-bold tabular-nums leading-tight {{ $ovrColor }}">{{ $ovr }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <div class="w-14 h-1.5 rounded-full bg-slate-700/80 overflow-hidden">
                                        <div class="h-full rounded-full" style="width:{{ $fit }}%; background:{{ $fitColor }};"></div>
                                    </div>
                                    <span class="text-[10px] font-medium tabular-nums {{ $fitLabel }} w-7">{{ $fit }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Voltar --}}
        <div class="pt-2">
            <a href="{{ url()->previous() }}"
               class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-300 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                Voltar
            </a>
        </div>

    </div>

</x-app-layout>
