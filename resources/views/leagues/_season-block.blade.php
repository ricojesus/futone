{{--
    Partial: leagues/_season-block.blade.php
    Variables:
      $block       - the transition block array
      $typeLabel   - label for first division  (e.g. "Série A" or "A1")
      $secondLabel - label for second division (e.g. "Série B" or "A2")
--}}
@php
    $firstDivision   = $block['first_division'];
    $secondDivision  = $block['second_division'];
    $champion        = $block['champion'];
    $runnerUp        = $block['runner_up'];
    $relegated       = $block['relegated'];
    $promoted        = $block['promoted'];
    $firstStandings  = $block['first_standings'];
    $secondStandings = $block['second_standings'];

    $relegationSpots = $firstDivision?->relegation_spots ?? 0;
    $promotionSpots  = $secondDivision?->promotion_spots ?? 0;
    $firstCount      = $firstStandings->count();
@endphp

<div class="space-y-8">

    {{-- ── Champion + Runner-up ──────────────────────────────────────── --}}
    @if ($champion || $runnerUp)
        <div class="grid gap-4 sm:grid-cols-2">

            {{-- Champion --}}
            @if ($champion)
            <div class="rounded-2xl border border-yellow-500/50 bg-yellow-500/10 p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-2xl">👑</span>
                    <span class="text-xs font-bold uppercase tracking-widest text-yellow-400/70">Campeão · {{ $typeLabel }}</span>
                </div>
                <p class="text-xl font-extrabold text-yellow-300 leading-tight">{{ $champion->name }}</p>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-yellow-400/80">
                    <span><strong class="text-yellow-300">{{ $champion->points }}</strong> pts</span>
                    <span>{{ $champion->wins }}V {{ $champion->draws }}E {{ $champion->losses }}D</span>
                    <span>{{ $champion->goals_for }}&ndash;{{ $champion->goals_against }}</span>
                </div>
            </div>
            @endif

            {{-- Runner-up --}}
            @if ($runnerUp)
            <div class="rounded-2xl border border-slate-400/40 bg-slate-400/5 p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-2xl">🥈</span>
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-400/70">Vice-campeão · {{ $typeLabel }}</span>
                </div>
                <p class="text-xl font-extrabold text-slate-200 leading-tight">{{ $runnerUp->name }}</p>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-400">
                    <span><strong class="text-slate-300">{{ $runnerUp->points }}</strong> pts</span>
                    <span>{{ $runnerUp->wins }}V {{ $runnerUp->draws }}E {{ $runnerUp->losses }}D</span>
                    <span>{{ $runnerUp->goals_for }}&ndash;{{ $runnerUp->goals_against }}</span>
                </div>
            </div>
            @endif

        </div>
    @endif

    {{-- ── Promoted banner ────────────────────────────────────────────── --}}
    @if ($promoted->isNotEmpty())
        <div class="rounded-2xl border border-emerald-500/50 bg-emerald-500/10 p-5">
            <p class="mb-3 text-xs font-bold uppercase tracking-widest text-emerald-400">
                ↑ Promovidos para {{ $typeLabel }}
            </p>
            <div class="flex flex-wrap gap-2">
                @foreach ($promoted as $ct)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-sm font-semibold text-emerald-300">
                        ↑ {{ $ct->name }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── First division standings ────────────────────────────────────── --}}
    @if ($firstStandings->isNotEmpty())
        <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-500">
                Classificação Final · {{ $firstDivision?->name ?? $typeLabel }}
            </h3>
            <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 text-xs text-slate-500">
                            <th class="px-4 py-3 text-left w-8">#</th>
                            <th class="px-4 py-3 text-left">Time</th>
                            <th class="px-2 py-3 text-center" title="Jogos">J</th>
                            <th class="px-2 py-3 text-center" title="Vitórias">V</th>
                            <th class="px-2 py-3 text-center" title="Empates">E</th>
                            <th class="px-2 py-3 text-center" title="Derrotas">D</th>
                            <th class="px-2 py-3 text-center" title="Gols Pró">GP</th>
                            <th class="px-2 py-3 text-center" title="Gols Contra">GC</th>
                            <th class="px-2 py-3 text-center font-bold text-white" title="Pontos">P</th>
                            <th class="px-3 py-3 text-center">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($firstStandings as $pos => $ct)
                            @php
                                $isChampion  = $pos === 0;
                                $isRelZone   = $relegationSpots > 0 && $pos >= ($firstCount - $relegationSpots);
                                $rowBg = $isChampion ? 'bg-yellow-500/10' : ($isRelZone ? 'bg-red-500/5' : 'hover:bg-slate-800/50');
                                $jogos = $ct->wins + $ct->draws + $ct->losses;
                            @endphp
                            <tr class="{{ $rowBg }} transition">
                                <td class="px-4 py-2.5 text-slate-500 text-xs tabular-nums">
                                    @if ($isChampion)
                                        <span class="text-yellow-400">👑</span>
                                    @else
                                        {{ $pos + 1 }}
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 font-medium {{ $isChampion ? 'text-yellow-300' : ($isRelZone ? 'text-red-300' : 'text-white') }}">
                                    {{ $ct->name }}
                                </td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $jogos }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->wins }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->draws }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->losses }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->goals_for }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->goals_against }}</td>
                                <td class="px-2 py-2.5 text-center font-bold text-white tabular-nums">{{ $ct->points }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($isChampion)
                                        <span class="rounded-full border border-yellow-500/40 bg-yellow-500/10 px-2 py-0.5 text-xs font-semibold text-yellow-400">Campeão</span>
                                    @elseif ($isRelZone)
                                        <span class="rounded-full border border-red-500/40 bg-red-500/10 px-2 py-0.5 text-xs font-semibold text-red-400">↓ Rebaixado</span>
                                    @else
                                        <span class="text-slate-600 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ── Second division standings ───────────────────────────────────── --}}
    @if ($secondStandings->isNotEmpty())
        <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-500">
                Classificação Final · {{ $secondDivision?->name ?? $secondLabel }}
            </h3>
            <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 text-xs text-slate-500">
                            <th class="px-4 py-3 text-left w-8">#</th>
                            <th class="px-4 py-3 text-left">Time</th>
                            <th class="px-2 py-3 text-center" title="Jogos">J</th>
                            <th class="px-2 py-3 text-center" title="Vitórias">V</th>
                            <th class="px-2 py-3 text-center" title="Empates">E</th>
                            <th class="px-2 py-3 text-center" title="Derrotas">D</th>
                            <th class="px-2 py-3 text-center" title="Gols Pró">GP</th>
                            <th class="px-2 py-3 text-center" title="Gols Contra">GC</th>
                            <th class="px-2 py-3 text-center font-bold text-white" title="Pontos">P</th>
                            <th class="px-3 py-3 text-center">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($secondStandings as $pos => $ct)
                            @php
                                $isPromoZone = $promotionSpots > 0 && $pos < $promotionSpots;
                                $rowBg = $isPromoZone ? 'bg-emerald-500/5' : 'hover:bg-slate-800/50';
                                $jogos = $ct->wins + $ct->draws + $ct->losses;
                            @endphp
                            <tr class="{{ $rowBg }} transition">
                                <td class="px-4 py-2.5 text-slate-500 text-xs tabular-nums">{{ $pos + 1 }}</td>
                                <td class="px-4 py-2.5 font-medium {{ $isPromoZone ? 'text-emerald-300' : 'text-white' }}">
                                    {{ $ct->name }}
                                </td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $jogos }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->wins }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->draws }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->losses }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->goals_for }}</td>
                                <td class="px-2 py-2.5 text-center text-slate-400 tabular-nums">{{ $ct->goals_against }}</td>
                                <td class="px-2 py-2.5 text-center font-bold text-white tabular-nums">{{ $ct->points }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($isPromoZone)
                                        <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-400">↑ Promovido</span>
                                    @else
                                        <span class="text-slate-600 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
