@php
    $positionLabel = [
        'goalkeeper' => 'Goleiro',
        'defender'   => 'Zagueiro/Lateral',
        'midfielder' => 'Meio-campo',
        'forward'    => 'Atacante',
    ];
    $ovr = (int) round(($player->strength + $player->stamina) / 2);
    $suggestedFee = $player->market_value;
@endphp

<x-app-layout>

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">

            <div class="flex items-center gap-2 mb-4 text-xs text-slate-500">
                <a href="{{ route('leagues.show', $league) }}" class="hover:text-slate-300 transition">{{ $league->name }}</a>
                <span>/</span>
                <a href="{{ route('leagues.transfers.index', $league) }}" class="hover:text-slate-300 transition">Mercado</a>
                <span>/</span>
                <span class="text-slate-400">{{ $player->name }}</span>
            </div>

            <h1 class="text-2xl font-extrabold text-white">Proposta de Transferência</h1>
        </div>
    </div>

    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">

        @if (! $canBuy)
            <div class="rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                Seu elenco está cheio (máximo 25 jogadores). Libere um jogador antes de contratar.
            </div>
        @endif

        @if ($minContractAlert)
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-400">
                Este jogador ainda está no período mínimo de contrato (6 meses) e não pode ser transferido.
            </div>
        @endif

        {{-- ── Card do jogador ─────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
            <div class="flex items-start gap-6 flex-wrap">
                <div class="flex-1 min-w-0 space-y-4">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-xl font-bold text-white">{{ $player->name }}</h2>
                        <span class="rounded px-2 py-0.5 text-xs font-bold
                            {{ match($player->position) {
                                'goalkeeper' => 'bg-yellow-500/20 text-yellow-400',
                                'defender'   => 'bg-blue-500/20 text-blue-400',
                                'midfielder' => 'bg-green-500/20 text-green-400',
                                'forward'    => 'bg-red-500/20 text-red-400',
                                default      => 'bg-slate-700 text-slate-400',
                            } }}">
                            {{ $positionLabel[$player->position] ?? $player->position }}
                        </span>
                        @if ($player->isFreeAgent())
                            <span class="rounded px-2 py-0.5 text-xs font-bold bg-amber-500/20 text-amber-400">Free Agent</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="text-center rounded-xl bg-slate-800 p-3">
                            <div class="text-2xl font-extrabold text-white">{{ $ovr }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">OVR</div>
                        </div>
                        <div class="text-center rounded-xl bg-slate-800 p-3">
                            <div class="text-2xl font-extrabold text-white">{{ $player->age }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">Anos</div>
                        </div>
                        <div class="text-center rounded-xl bg-slate-800 p-3">
                            <div class="text-lg font-extrabold text-emerald-400">{{ $player->strength }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">Força</div>
                        </div>
                        <div class="text-center rounded-xl bg-slate-800 p-3">
                            <div class="text-lg font-extrabold text-blue-400">{{ $player->stamina }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">Stamina</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-6 text-sm">
                        <div>
                            <span class="text-slate-500">Clube atual:</span>
                            <span class="text-slate-200 ml-1">
                                {{ $sellerLeagueTeam?->name ?? 'Sem clube' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-500">Salário/semana:</span>
                            <span class="text-slate-200 ml-1">R$ {{ number_format($player->wage, 0, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Valor de mercado:</span>
                            <span class="text-emerald-400 ml-1 font-semibold">R$ {{ number_format($player->market_value, 0, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Salário mínimo est.:</span>
                            <span class="text-slate-300 ml-1">R$ {{ number_format($suggestedWage, 0, ',', '.') }}/sem</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Formulário de proposta ───────────────────────────────────── --}}
        @if ($canBuy && ! $minContractAlert)
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h3 class="text-base font-semibold text-white mb-5">Detalhes da Proposta</h3>

                <form method="POST" action="{{ route('leagues.transfers.store', $league) }}"
                      class="space-y-5">
                    @csrf
                    <input type="hidden" name="player_id" value="{{ $player->id }}">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">

                        {{-- Taxa de transferência --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Taxa de transferência (R$)
                            </label>
                            <input type="number" name="offered_fee"
                                   value="{{ old('offered_fee', $player->isFreeAgent() ? 0 : $suggestedFee) }}"
                                   min="0" step="100000"
                                   @if ($player->isFreeAgent()) readonly @endif
                                   class="w-full rounded-xl bg-slate-800 border border-slate-700 px-4 py-2.5 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none @if($player->isFreeAgent()) opacity-50 cursor-not-allowed @endif">
                            @if ($player->isFreeAgent())
                                <p class="text-xs text-slate-500">Free agent: taxa zero.</p>
                            @else
                                <p class="text-xs text-slate-500">Valor de mercado: R$ {{ number_format($suggestedFee, 0, ',', '.') }}</p>
                            @endif
                            @error('offered_fee')
                                <p class="text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Salário --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Salário por semana (R$)
                            </label>
                            <input type="number" name="offered_wage"
                                   value="{{ old('offered_wage', $suggestedWage) }}"
                                   min="1" step="1000"
                                   class="w-full rounded-xl bg-slate-800 border border-slate-700 px-4 py-2.5 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none">
                            <p class="text-xs text-slate-500">Estimativa mínima: R$ {{ number_format($suggestedWage, 0, ',', '.') }}</p>
                            @error('offered_wage')
                                <p class="text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Duração do contrato --}}
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Duração do contrato (anos)
                            </label>
                            <select name="contract_years"
                                    class="w-full rounded-xl bg-slate-800 border border-slate-700 px-4 py-2.5 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none">
                                @foreach ([1,2,3,4,5] as $y)
                                    <option value="{{ $y }}" @selected(old('contract_years', 3) == $y)>{{ $y }} {{ $y === 1 ? 'ano' : 'anos' }}</option>
                                @endforeach
                            </select>
                            @error('contract_years')
                                <p class="text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <button type="submit"
                                class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 transition">
                            Enviar Proposta
                        </button>
                        <a href="{{ route('leagues.transfers.index', $league) }}"
                           class="text-sm text-slate-400 hover:text-slate-200 transition">
                            Cancelar
                        </a>
                    </div>

                    <div class="rounded-xl bg-slate-800/60 border border-slate-700 px-4 py-3 text-xs text-slate-400 space-y-1">
                        <p><span class="text-slate-300 font-semibold">Como funciona:</span> Sua proposta é avaliada pelo clube (se for CPU) e depois pelo jogador.</p>
                        <p>O jogador pode aceitar, fazer uma contra-proposta de salário, ou recusar. Você será notificado no painel de propostas.</p>
                        <p>Seu orçamento atual: <span class="text-emerald-400 font-semibold">R$ {{ number_format($myLeagueTeam->budget, 0, ',', '.') }}</span></p>
                    </div>
                </form>
            </div>
        @endif

    </div>
</x-app-layout>
