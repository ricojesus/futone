@php
    $positionLabel = [
        'goalkeeper' => 'GK',
        'defender'   => 'ZA',
        'midfielder' => 'ME',
        'forward'    => 'AT',
    ];
    $positionName = [
        'goalkeeper' => 'Goleiro',
        'defender'   => 'Zagueiro/Lateral',
        'midfielder' => 'Meio-campo',
        'forward'    => 'Atacante',
    ];
@endphp

<x-app-layout>

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">

            <div class="flex items-center gap-2 mb-4 text-xs text-slate-500">
                <a href="{{ route('leagues.show', $league) }}" class="hover:text-slate-300 transition">{{ $league->name }}</a>
                <span>/</span>
                <span class="text-slate-400">Mercado de Transferências</span>
            </div>

            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-extrabold text-white">Mercado de Transferências</h1>
                    <p class="mt-1 text-sm text-slate-400">Pesquise jogadores disponíveis e faça propostas</p>
                </div>
                <a href="{{ route('leagues.transfers.offers', $league) }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 transition">
                    Minhas Propostas
                </a>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">

        @if (session('success'))
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Filtros ─────────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <form method="GET" action="{{ route('leagues.transfers.index', $league) }}"
                  class="flex flex-wrap gap-3 items-end">

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-slate-400">Posição</label>
                    <select name="position"
                            class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none">
                        <option value="">Todas</option>
                        @foreach ($positionName as $key => $label)
                            <option value="{{ $key }}" @selected(request('position') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-slate-400">Idade máx.</label>
                    <input type="number" name="age_max" value="{{ request('age_max') }}"
                           min="16" max="45" placeholder="45"
                           class="w-24 rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-slate-400">OVR mín.</label>
                    <input type="number" name="overall_min" value="{{ request('overall_min') }}"
                           min="1" max="99" placeholder="—"
                           class="w-24 rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-slate-400">Valor máx. (M)</label>
                    <input type="number" name="value_max" value="{{ request('value_max') }}"
                           min="0" placeholder="—"
                           class="w-28 rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-slate-400">Divisão Nacional</label>
                    <select name="division"
                            class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-1.5 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none">
                        <option value="">Todas</option>
                        <option value="first"  @selected(request('division') === 'first')>Série A</option>
                        <option value="second" @selected(request('division') === 'second')>Série B</option>
                        <option value="none"   @selected(request('division') === 'none')>Estadual</option>
                    </select>
                </div>

                <button type="submit"
                        class="rounded-lg bg-slate-700 px-4 py-1.5 text-sm font-semibold text-white hover:bg-slate-600 transition">
                    Filtrar
                </button>

                @if (request()->hasAny(['position','age_max','age_min','overall_min','value_max','division']))
                    <a href="{{ route('leagues.transfers.index', $league) }}"
                       class="text-xs text-slate-500 hover:text-slate-300 self-end pb-2 transition">
                        Limpar filtros
                    </a>
                @endif
            </form>
        </div>

        {{-- ── Tabela de jogadores ─────────────────────────────────────── --}}
        <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Jogador</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Pos</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Idade</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">OVR</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Valor de Mercado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Clube</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($players as $player)
                            @php
                                $ovr = (int) round(($player->strength + $player->stamina) / 2);
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-white">{{ $player->name }}</div>
                                    @if ($player->isFreeAgent())
                                        <span class="text-xs text-amber-400">Free Agent</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block rounded px-1.5 py-0.5 text-xs font-bold
                                        {{ match($player->position) {
                                            'goalkeeper' => 'bg-yellow-500/20 text-yellow-400',
                                            'defender'   => 'bg-blue-500/20 text-blue-400',
                                            'midfielder' => 'bg-green-500/20 text-green-400',
                                            'forward'    => 'bg-red-500/20 text-red-400',
                                            default      => 'bg-slate-700 text-slate-400',
                                        } }}">
                                        {{ $positionLabel[$player->position] ?? '?' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-slate-300">{{ $player->age }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-bold {{ $ovr >= 75 ? 'text-emerald-400' : ($ovr >= 60 ? 'text-slate-200' : 'text-slate-500') }}">
                                        {{ $ovr }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-300">
                                    R$ {{ number_format($player->market_value, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($player->leagueTeam)
                                        <div class="flex items-center gap-2">
                                            <x-team-badge :team="$player->leagueTeam" size="sm" />
                                            <span class="text-slate-400 text-xs">{{ $player->leagueTeam->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-600 text-xs">Sem clube</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('leagues.transfers.show', [$league, $player]) }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-emerald-600/20 border border-emerald-500/30 px-3 py-1 text-xs font-semibold text-emerald-400 hover:bg-emerald-600/40 transition">
                                        Proposta
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                                    Nenhum jogador encontrado com os filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($players->hasPages())
                <div class="border-t border-slate-800 px-4 py-3">
                    {{ $players->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
