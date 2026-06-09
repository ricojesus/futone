<x-app-layout>

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">

            <div class="flex items-center gap-2 mb-4 text-xs text-slate-500">
                <a href="{{ route('leagues.show', $league) }}" class="hover:text-slate-300 transition">{{ $league->name }}</a>
                <span>/</span>
                <a href="{{ route('leagues.transfers.index', $league) }}" class="hover:text-slate-300 transition">Mercado</a>
                <span>/</span>
                <span class="text-slate-400">Minhas Propostas</span>
            </div>

            <h1 class="text-2xl font-extrabold text-white">Minhas Propostas</h1>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">

        @if (session('success'))
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Contra-propostas recebidas ───────────────────────────────── --}}
        @if ($countered->isNotEmpty())
            <div>
                <h2 class="text-base font-semibold text-amber-400 mb-3 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    Contra-propostas Pendentes ({{ $countered->count() }})
                </h2>

                <div class="space-y-3">
                    @foreach ($countered as $offer)
                        <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
                            <div class="flex items-start justify-between gap-4 flex-wrap">
                                <div class="space-y-1">
                                    <div class="font-semibold text-white text-base">{{ $offer->player->name }}</div>
                                    <div class="text-xs text-slate-400">
                                        Proposta recebida de
                                        <span class="text-slate-200">{{ $offer->buyerTeam->leagueTeam->name }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-4 mt-2 text-sm">
                                        <span>
                                            <span class="text-slate-500">Taxa proposta:</span>
                                            <span class="text-slate-200 ml-1">R$ {{ number_format($offer->offered_fee, 0, ',', '.') }}</span>
                                        </span>
                                        <span>
                                            <span class="text-slate-500">Salário proposto:</span>
                                            <span class="text-slate-200 ml-1">R$ {{ number_format($offer->offered_wage, 0, ',', '.') }}/sem</span>
                                        </span>
                                        <span>
                                            <span class="text-slate-500">Jogador quer:</span>
                                            <span class="text-amber-400 font-semibold ml-1">R$ {{ number_format($offer->counter_price, 0, ',', '.') }}/sem</span>
                                        </span>
                                        <span>
                                            <span class="text-slate-500">Salário atual:</span>
                                            <span class="text-slate-200 ml-1">R$ {{ number_format($offer->player->wage, 0, ',', '.') }}/sem</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Ação do manager --}}
                            <div class="mt-4 border-t border-slate-700 pt-4"
                                 x-data="{ action: 'retain' }">
                                <p class="text-xs text-slate-400 mb-3">
                                    O jogador está inclinado a aceitar a proposta. Você pode oferecer um aumento de salário para retê-lo, ou aceitar a transferência.
                                </p>
                                <form method="POST"
                                      action="{{ route('leagues.transfers.respond', [$league, $offer]) }}">
                                    @csrf
                                    <div class="flex flex-wrap gap-3 items-end">
                                        <div>
                                            <label class="text-xs text-slate-400 block mb-1">Sua decisão</label>
                                            <select name="action" x-model="action"
                                                    class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none">
                                                <option value="retain">Oferecer retenção salarial</option>
                                                <option value="release">Aceitar a transferência</option>
                                            </select>
                                        </div>

                                        <div x-show="action === 'retain'" x-cloak>
                                            <label class="text-xs text-slate-400 block mb-1">Novo salário (R$/sem)</label>
                                            <input type="number" name="retention_wage"
                                                   value="{{ old('retention_wage', $offer->counter_price) }}"
                                                   min="{{ $offer->player->wage }}" step="1000"
                                                   class="rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none w-40">
                                        </div>

                                        <button type="submit"
                                                class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                                                :class="action === 'retain'
                                                    ? 'bg-emerald-600 text-white hover:bg-emerald-500'
                                                    : 'bg-red-600/30 border border-red-500/30 text-red-400 hover:bg-red-600/50'">
                                            <span x-show="action === 'retain'">Propor Retenção</span>
                                            <span x-show="action === 'release'" x-cloak>Confirmar Transferência</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── Propostas enviadas ───────────────────────────────────────── --}}
        <div>
            <h2 class="text-base font-semibold text-slate-200 mb-3">Propostas Enviadas</h2>

            @if ($sent->isEmpty())
                <div class="rounded-2xl border border-slate-700 bg-slate-900 px-6 py-12 text-center text-slate-500">
                    Nenhuma proposta enviada ainda.
                    <a href="{{ route('leagues.transfers.index', $league) }}" class="text-emerald-400 hover:text-emerald-300 ml-1">Pesquisar jogadores</a>
                </div>
            @else
                <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Jogador</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Taxa</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Salário/sem</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($sent as $offer)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-white">{{ $offer->player->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $offer->player->leagueTeam?->name ?? 'Sem clube' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-300">
                                        R$ {{ number_format($offer->offered_fee, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-300">
                                        R$ {{ number_format($offer->offered_wage, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            $statusClasses = match($offer->status) {
                                                'accepted'        => 'bg-emerald-500/20 text-emerald-400',
                                                'rejected_team',
                                                'rejected_player',
                                                'withdrawn'       => 'bg-red-500/20 text-red-400',
                                                'countered'       => 'bg-amber-500/20 text-amber-400',
                                                default           => 'bg-slate-700 text-slate-400',
                                            };
                                        @endphp
                                        <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClasses }}">
                                            {{ $offer->statusLabel() }}
                                        </span>
                                        @if ($offer->isCountered())
                                            <div class="text-xs text-amber-400 mt-0.5">
                                                Pede R$ {{ number_format($offer->counter_price, 0, ',', '.') }}/sem
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-slate-500">
                                        {{ $offer->created_at->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
