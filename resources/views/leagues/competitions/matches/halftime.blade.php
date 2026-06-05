@php
    $homeName       = $match->homeTeam->name;
    $awayName       = $match->awayTeam->name;
    $myHome         = $side === 'home';
    $myAway         = $side === 'away';
    $goalEvents     = collect($events)->filter(fn($e) => ($e['type'] ?? '') === 'goal');
    $awayPossession = 100 - $homePossession;
    $opponentName   = $myHome ? $awayName : $homeName;
@endphp

<x-app-layout>

<div x-data="halftimeReplay({
        events:         {{ Js::from($events) }},
        finalHomeScore: {{ $homeScore }},
        finalAwayScore: {{ $awayScore }},
    })">

    {{-- ── Top bar ──────────────────────────────────────────────────────── --}}
    <div class="sticky top-0 z-20 border-b border-slate-800 bg-slate-900/90 backdrop-blur-sm">
        <div class="mx-auto max-w-5xl px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <a href="{{ route('leagues.show', $league) }}" class="hover:text-slate-300 transition">{{ $league->name }}</a>
                <span class="text-slate-700">/</span>
                <a href="{{ route('competitions.show', [$league, $competition]) }}" class="hover:text-slate-300 transition">{{ $competition->name }}</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400">Rodada {{ $match->round }}</span>
            </div>

            {{-- Badge: ao vivo durante replay, intervalo depois --}}
            <span x-show="!replayDone"
                  class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-400">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span x-text="currentMinute + '''">1'</span>
            </span>
            <span x-show="replayDone" x-transition
                  class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                Intervalo
            </span>
        </div>
    </div>

    {{-- ── Scoreboard ─────────────────────────────────────────────────── --}}
    <div class="bg-gradient-to-b from-slate-900 to-slate-950 border-b border-slate-800">
        <div class="mx-auto max-w-5xl px-4 pt-8 pb-6">
            <div class="grid grid-cols-3 items-center gap-4">

                {{-- Home --}}
                <div class="text-right space-y-1">
                    <div class="flex items-center justify-end gap-3">
                        <p class="text-xl font-extrabold sm:text-3xl leading-tight {{ $myHome ? 'text-emerald-400' : 'text-white' }}">
                            {{ $homeName }}
                        </p>
                        <x-team-badge :team="$match->homeTeam" size="lg" />
                    </div>
                    @if ($myHome)
                        <span class="inline-block rounded-full bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5 text-[10px] font-semibold text-emerald-400">Seu time</span>
                    @endif
                </div>

                {{-- Placar + indicador --}}
                <div class="text-center">
                    <div class="flex items-center justify-center gap-4">
                        <span x-text="homeScore" class="text-5xl font-black tabular-nums text-white">0</span>
                        <span class="text-2xl text-slate-600">×</span>
                        <span x-text="awayScore" class="text-5xl font-black tabular-nums text-white">0</span>
                    </div>
                    <div class="mt-2">
                        <span x-show="!replayDone"
                              class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-400">
                            <span x-text="currentMinute + '' · 1º Tempo'">1' · 1º Tempo</span>
                        </span>
                        <span x-show="replayDone" x-transition
                              class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-400">
                            45' · Intervalo
                        </span>
                    </div>
                </div>

                {{-- Away --}}
                <div class="text-left space-y-1">
                    <div class="flex items-center gap-3">
                        <x-team-badge :team="$match->awayTeam" size="lg" />
                        <p class="text-xl font-extrabold sm:text-3xl leading-tight {{ $myAway ? 'text-emerald-400' : 'text-white' }}">
                            {{ $awayName }}
                        </p>
                    </div>
                    @if ($myAway)
                        <span class="inline-block rounded-full bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5 text-[10px] font-semibold text-emerald-400">Seu time</span>
                    @endif
                </div>
            </div>

            {{-- Barra de progresso do replay --}}
            <div x-show="!replayDone" class="mt-5 mx-auto max-w-xs">
                <div class="flex items-center gap-3 mb-1.5">
                    <div class="flex-1 h-1 rounded-full bg-slate-800 overflow-hidden">
                        <div class="h-full bg-emerald-500 transition-all duration-500 rounded-full"
                             :style="'width:' + progress + '%'"></div>
                    </div>
                    <span class="text-[10px] text-slate-600 tabular-nums" x-text="progress + '%'"></span>
                </div>
                <div class="flex items-center justify-center">
                    <div class="flex items-center gap-1 rounded-xl border border-slate-700 bg-slate-800/60 p-1">
                        <template x-for="s in [1, 2, 5, 15]" :key="s">
                            <button @click="setSpeed(s)"
                                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
                                    :class="speed === s ? 'bg-slate-600 text-white' : 'text-slate-500 hover:text-slate-300'"
                                    x-text="s === 15 ? 'MAX' : s + '×'">
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Body ────────────────────────────────────────────────────────── --}}
    <div class="mx-auto max-w-5xl px-4 py-6">

        {{-- ══ FASE REPLAY: feed minuto a minuto ══════════════════════════ --}}
        <div x-show="!replayDone">
            <div class="space-y-2 max-h-[55vh] overflow-y-auto pr-1" id="halftime-feed">
                <template x-if="narrations.length === 0">
                    <div class="rounded-2xl border border-dashed border-slate-800 px-6 py-10 text-center">
                        <p class="text-slate-600 text-sm animate-pulse">Apito inicial… aguarde</p>
                    </div>
                </template>
                <template x-for="(event, idx) in narrations" :key="idx">
                    <div class="flex gap-3 rounded-xl border px-4 py-3"
                         :class="{
                             'border-emerald-500/50 bg-emerald-500/10': event.type === 'goal',
                             'border-amber-500/30  bg-amber-500/5':    event.type === 'shot_saved' || event.type === 'halftime',
                             'border-slate-800/60  bg-slate-900/40':   event.type !== 'goal' && event.type !== 'shot_saved' && event.type !== 'halftime',
                         }">
                        <span class="shrink-0 w-8 pt-0.5 text-right text-xs font-bold tabular-nums"
                              :class="{
                                  'text-emerald-400': event.type === 'goal',
                                  'text-amber-400':   event.type === 'shot_saved' || event.type === 'halftime',
                                  'text-slate-600':   event.type !== 'goal' && event.type !== 'shot_saved' && event.type !== 'halftime',
                              }"
                              x-text="event.play + '\''"></span>
                        <div class="flex-1 text-sm text-slate-300 leading-snug">
                            <span x-show="event.type === 'goal'" class="mr-1 font-bold text-emerald-400">⚽ GOOOOL!</span>
                            <span x-show="event.type === 'goal' && event.scorer_name"
                                  x-text="event.scorer_name + '!'"
                                  class="mr-1 font-semibold text-white"></span>
                            <span x-show="event.type === 'halftime'" class="mr-1 font-bold text-amber-400">🔔 INTERVALO</span>
                            <span x-show="event.type === 'shot_saved'" class="mr-1 font-semibold text-amber-400 text-xs">DEFESA!</span>
                            <span x-show="event.type === 'shot_missed'" class="mr-1 font-semibold text-slate-500 text-xs">FORA!</span>
                            <span x-text="event.narration"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ══ FASE INTERVALO: painel completo após replay ═════════════════ --}}
        <div x-show="replayDone" x-transition.opacity.duration.500ms>

            {{-- Banner de intervalo --}}
            <div class="mb-6 rounded-2xl border border-amber-500/30 bg-amber-500/5 px-5 py-4 flex items-center gap-3">
                <span class="text-2xl">🔔</span>
                <div>
                    <p class="font-bold text-amber-400 text-sm">Intervalo!</p>
                    <p class="text-xs text-slate-500 mt-0.5">Analise as estatísticas e faça suas substituições antes do 2º tempo.</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                {{-- Coluna principal --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Gols do 1º tempo --}}
                    <div>
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">Gols — 1º Tempo</h2>

                        @if ($goalEvents->isEmpty())
                            <div class="rounded-2xl border border-dashed border-slate-800 px-6 py-5 text-center">
                                <p class="text-slate-600 text-sm">Nenhum gol no primeiro tempo.</p>
                            </div>
                        @else
                            <div class="rounded-2xl border border-slate-800 bg-slate-900 divide-y divide-slate-800 overflow-hidden">
                                @foreach ($goalEvents as $goal)
                                    @php $isHome = ($goal['team'] ?? '') === 'home'; @endphp
                                    <div class="flex items-center gap-4 px-5 py-3">
                                        <span class="shrink-0 text-sm font-bold tabular-nums text-emerald-400 w-10">{{ $goal['play'] ?? '—' }}'</span>
                                        @if ($isHome)
                                            <div class="flex-1">
                                                <p class="font-semibold text-white leading-tight text-sm">{{ $homeName }}</p>
                                                @if (! empty($goal['scorer_name']))
                                                    <p class="text-xs text-emerald-400/80">{{ $goal['scorer_name'] }}</p>
                                                @endif
                                            </div>
                                            <span class="text-slate-700">⚽</span>
                                            <span class="flex-1 text-right text-slate-600 text-sm">{{ $awayName }}</span>
                                        @else
                                            <span class="flex-1 text-slate-600 text-sm">{{ $homeName }}</span>
                                            <span class="text-slate-700">⚽</span>
                                            <div class="flex-1 text-right">
                                                <p class="font-semibold text-white leading-tight text-sm">{{ $awayName }}</p>
                                                @if (! empty($goal['scorer_name']))
                                                    <p class="text-xs text-emerald-400/80">{{ $goal['scorer_name'] }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Narração 1º tempo (acordeão) --}}
                    <div x-data="{ open: false }">
                        <button @click="open = !open"
                            class="mb-3 flex w-full items-center justify-between text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-slate-300 transition">
                            <span>Narração — 1º Tempo</span>
                            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div x-show="open" x-collapse class="space-y-2">
                            @forelse ($events as $event)
                                <div class="flex gap-3 rounded-xl border px-4 py-3
                                    @if (($event['type'] ?? '') === 'goal') border-emerald-500/50 bg-emerald-500/10
                                    @elseif (($event['type'] ?? '') === 'halftime') border-amber-500/30 bg-amber-500/5
                                    @elseif (($event['type'] ?? '') === 'shot_saved') border-amber-500/30 bg-amber-500/5
                                    @else border-slate-800/60 bg-slate-900/40 @endif">
                                    <span class="shrink-0 w-8 pt-0.5 text-right text-xs font-bold tabular-nums
                                        @if (($event['type'] ?? '') === 'goal') text-emerald-400
                                        @elseif (($event['type'] ?? '') === 'halftime') text-amber-400
                                        @elseif (($event['type'] ?? '') === 'shot_saved') text-amber-400
                                        @else text-slate-600 @endif">
                                        {{ $event['play'] ?? '' }}'
                                    </span>
                                    <div class="flex-1 text-sm text-slate-300 leading-snug">
                                        @if (($event['type'] ?? '') === 'goal')
                                            <span class="font-bold text-emerald-400 mr-1">⚽ GOOOOL!</span>
                                            @if (! empty($event['scorer_name']))
                                                <span class="font-semibold text-white mr-1">{{ $event['scorer_name'] }}!</span>
                                            @endif
                                        @elseif (($event['type'] ?? '') === 'halftime')
                                            <span class="font-bold text-amber-400 mr-1">🔔 INTERVALO</span>
                                        @elseif (($event['type'] ?? '') === 'shot_saved')
                                            <span class="font-semibold text-amber-400 mr-1 text-xs">DEFESA!</span>
                                        @elseif (($event['type'] ?? '') === 'shot_missed')
                                            <span class="font-semibold text-slate-500 mr-1 text-xs">FORA!</span>
                                        @endif
                                        {{ $event['narration'] ?? '' }}
                                    </div>
                                </div>
                            @empty
                                <p class="text-slate-600 text-sm">Sem narração disponível.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- ══ PAINEL AGUARDANDO (HvH — já confirmei, esperando adversário) ══ --}}
                    @if ($isHumanVsHuman && $myReady && ! $otherReady)
                    <div
                        x-data="hvhWaiting({
                            secondsLeft:  {{ $secondsLeft }},
                            statusUrl:    '{{ $statusUrl }}',
                            resumeUrl:    '{{ route('matches.halftime.resume', [$league, $competition, $match]) }}',
                            otherReady:   false,
                        })"
                        x-init="startPolling()"
                        @destroy.window="stopPolling()"
                    >
                        {{-- Banner aguardando --}}
                        <div class="mb-4 rounded-2xl border border-violet-500/30 bg-violet-500/5 px-5 py-5 text-center space-y-3">
                            <div class="flex items-center justify-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-violet-400 animate-pulse"></span>
                                <p class="font-bold text-violet-300 text-sm">Aguardando {{ $opponentName }}</p>
                            </div>
                            <p class="text-xs text-slate-500">Suas substituições foram salvas. O 2º tempo começa assim que o adversário confirmar ou o tempo esgotar.</p>

                            {{-- Countdown ring --}}
                            <div class="flex items-center justify-center gap-3">
                                <div class="relative flex items-center justify-center w-16 h-16">
                                    <svg class="absolute inset-0 w-full h-full -rotate-90" viewBox="0 0 36 36">
                                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#1e293b" stroke-width="3"/>
                                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#7c3aed" stroke-width="3"
                                                stroke-dasharray="100"
                                                :stroke-dashoffset="100 - (secondsLeft / 60 * 100)"
                                                style="transition: stroke-dashoffset 1s linear;"/>
                                    </svg>
                                    <span class="text-lg font-black tabular-nums text-white" x-text="secondsLeft"></span>
                                </div>
                                <div class="text-left">
                                    <p class="text-xs text-slate-500 leading-snug">segundos até o<br>início automático</p>
                                </div>
                            </div>

                            {{-- Adversário confirmou --}}
                            <div x-show="otherReady" x-transition class="text-xs font-semibold text-emerald-400">
                                ✓ Adversário confirmou! Iniciando…
                            </div>
                        </div>

                        {{-- Formulário de força (auto-submit quando countdown chega a 0) --}}
                        <form
                            action="{{ route('matches.halftime.resume', [$league, $competition, $match]) }}"
                            method="POST"
                            x-ref="forceForm"
                        >
                            @csrf
                            <input type="hidden" name="force" value="1">
                        </form>
                    </div>

                    {{-- ══ PAINEL SUBSTITUIÇÕES (HvCPU ou HvH ainda não confirmado) ══ --}}
                    @elseif ($canResume && $lineup && $starters->isNotEmpty())
                    <div
                        x-data="{
                            subs: [],
                            maxSubs: 5,
                            pendingOut: null,
                            @if ($isHumanVsHuman)
                            secondsLeft: {{ $secondsLeft }},
                            countdownTimer: null,
                            @endif
                            addSub(outId, inId) {
                                if (this.subs.length >= this.maxSubs) return;
                                if (this.subs.find(s => s.out === outId || s.in === inId)) return;
                                this.subs.push({ out: outId, in: inId });
                                this.pendingOut = null;
                            },
                            removeSub(outId) {
                                this.subs = this.subs.filter(s => s.out !== outId);
                            },
                            isOut(id)  { return this.subs.some(s => s.out === id); },
                            isIn(id)   { return this.subs.some(s => s.in  === id); },
                            subsCount() { return this.subs.length; },
                        }"
                        @if ($isHumanVsHuman)
                        x-init="
                            countdownTimer = setInterval(() => {
                                if (secondsLeft > 0) {
                                    secondsLeft--;
                                } else {
                                    clearInterval(countdownTimer);
                                    $refs.subsForm.requestSubmit();
                                }
                            }, 1000);
                        "
                        @endif
                    >
                        {{-- Banner HvH: aviso de coordenação --}}
                        @if ($isHumanVsHuman)
                        <div class="mb-4 rounded-2xl border border-violet-500/20 bg-violet-500/5 px-4 py-3 flex items-start gap-3">
                            <span class="text-violet-400 text-lg mt-0.5">👥</span>
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-violet-300">Jogo entre humanos</p>
                                <p class="text-xs text-slate-500 mt-0.5">O 2º tempo começa quando <strong class="text-slate-300">ambos</strong> confirmarem ou o tempo esgotar.</p>
                            </div>
                            <div class="shrink-0 text-center">
                                <span class="text-xl font-black tabular-nums text-white" x-text="secondsLeft">{{ $secondsLeft }}</span>
                                <p class="text-[10px] text-slate-600 leading-none">seg</p>
                            </div>
                        </div>
                        @if ($otherReady)
                        <div class="mb-4 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-2.5 flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <p class="text-xs font-semibold text-emerald-400">{{ $opponentName }} já confirmou! Confirme para iniciar.</p>
                        </div>
                        @endif
                        @endif

                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">
                            Substituições
                            <span class="ml-1 font-normal text-slate-600">(máx. 5)</span>
                        </h2>

                        {{-- Titulares --}}
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden mb-3">
                            <div class="border-b border-slate-800 px-4 py-2.5">
                                <p class="text-xs font-semibold text-slate-400">Titulares em campo</p>
                            </div>
                            <div class="divide-y divide-slate-800/60">
                                @foreach ($starters as $player)
                                    @php
                                        $fit = (int) ($player->fitness ?? 100);
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
                                        $ovr = (int) ($player->strength ?? 0);
                                        $ovrLabel = match(true) {
                                            $ovr >= 80 => 'text-amber-300',
                                            $ovr >= 65 => 'text-emerald-400',
                                            $ovr >= 50 => 'text-slate-300',
                                            default    => 'text-slate-500',
                                        };
                                    @endphp
                                    <div class="flex items-center gap-3 px-4 py-2.5"
                                         :class="isOut('{{ $player->id }}') ? 'opacity-40' : ''">
                                        <span class="shrink-0 w-6 text-center text-[10px] font-bold uppercase text-slate-600">
                                            {{ match($player->position) {
                                                'goalkeeper' => 'GK',
                                                'defender'   => 'ZA',
                                                'midfielder' => 'ME',
                                                'forward'    => 'AT',
                                                default      => '—',
                                            } }}
                                        </span>
                                        <span class="flex-1 text-sm font-medium text-white">{{ $player->name }}</span>
                                        {{-- OVR --}}
                                        <div class="shrink-0 flex flex-col items-center w-9">
                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-600 leading-none">OVR</span>
                                            <span class="text-sm font-bold tabular-nums leading-tight {{ $ovrLabel }}">{{ $ovr }}</span>
                                        </div>
                                        {{-- Saúde --}}
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <div class="w-14 h-1.5 rounded-full bg-slate-700/80 overflow-hidden">
                                                <div class="h-full rounded-full" style="width:{{ $fit }}%; background:{{ $fitColor }};"></div>
                                            </div>
                                            <span class="text-[10px] font-medium tabular-nums {{ $fitLabel }}">{{ $fit }}%</span>
                                        </div>

                                        <template x-if="! isOut('{{ $player->id }}') && subs.length < maxSubs">
                                            <button
                                                @click="pendingOut = pendingOut === '{{ $player->id }}' ? null : '{{ $player->id }}'"
                                                :class="pendingOut === '{{ $player->id }}' ? 'border-amber-500/60 bg-amber-500/10 text-amber-400' : 'border-slate-700 text-slate-500 hover:border-slate-600 hover:text-slate-300'"
                                                class="shrink-0 rounded-lg border px-2 py-1 text-xs transition">
                                                ↓ Substituir
                                            </button>
                                        </template>
                                        <template x-if="isOut('{{ $player->id }}')">
                                            <button @click="removeSub('{{ $player->id }}')"
                                                class="shrink-0 rounded-lg border border-red-500/40 bg-red-500/10 px-2 py-1 text-xs text-red-400 hover:bg-red-500/20 transition">
                                                ✕ Desfazer
                                            </button>
                                        </template>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Reservas --}}
                        @if ($bench->isNotEmpty())
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden mb-4">
                            <div class="border-b border-slate-800 px-4 py-2.5">
                                <p class="text-xs font-semibold text-slate-400">Reservas</p>
                            </div>
                            <div class="divide-y divide-slate-800/60">
                                @foreach ($bench as $player)
                                    @php
                                        $fit = (int) ($player->fitness ?? 100);
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
                                        $ovr = (int) ($player->strength ?? 0);
                                        $ovrLabel = match(true) {
                                            $ovr >= 80 => 'text-amber-300',
                                            $ovr >= 65 => 'text-emerald-400',
                                            $ovr >= 50 => 'text-slate-300',
                                            default    => 'text-slate-500',
                                        };
                                    @endphp
                                    <div class="flex items-center gap-3 px-4 py-2.5"
                                         :class="isIn('{{ $player->id }}') ? 'opacity-40' : ''">
                                        <span class="shrink-0 w-6 text-center text-[10px] font-bold uppercase text-slate-600">
                                            {{ match($player->position) {
                                                'goalkeeper' => 'GK',
                                                'defender'   => 'ZA',
                                                'midfielder' => 'ME',
                                                'forward'    => 'AT',
                                                default      => '—',
                                            } }}
                                        </span>
                                        <span class="flex-1 text-sm font-medium text-white">{{ $player->name }}</span>
                                        {{-- OVR --}}
                                        <div class="shrink-0 flex flex-col items-center w-9">
                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-600 leading-none">OVR</span>
                                            <span class="text-sm font-bold tabular-nums leading-tight {{ $ovrLabel }}">{{ $ovr }}</span>
                                        </div>
                                        {{-- Saúde --}}
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <div class="w-14 h-1.5 rounded-full bg-slate-700/80 overflow-hidden">
                                                <div class="h-full rounded-full" style="width:{{ $fit }}%; background:{{ $fitColor }};"></div>
                                            </div>
                                            <span class="text-[10px] font-medium tabular-nums {{ $fitLabel }}">{{ $fit }}%</span>
                                        </div>

                                        <template x-if="pendingOut && ! isIn('{{ $player->id }}')">
                                            <button
                                                @click="addSub(pendingOut, '{{ $player->id }}')"
                                                class="shrink-0 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 text-xs text-emerald-400 hover:bg-emerald-500/20 transition">
                                                ↑ Entrar
                                            </button>
                                        </template>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Substituições confirmadas --}}
                        <template x-if="subs.length > 0">
                            <div class="mb-4 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3 space-y-1.5">
                                <p class="text-xs font-semibold text-emerald-400 mb-2">
                                    Substituições confirmadas (<span x-text="subs.length"></span>/5)
                                </p>
                                <template x-for="(sub, idx) in subs" :key="idx">
                                    <div class="flex items-center gap-2 text-xs text-slate-400">
                                        <span class="text-red-400">↓</span>
                                        <span x-text="'#' + (idx+1) + ' substituição'"></span>
                                        <button @click="removeSub(sub.out)" class="ml-auto text-slate-600 hover:text-red-400 transition">✕</button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Formulário de envio --}}
                        <form
                            action="{{ route('matches.halftime.resume', [$league, $competition, $match]) }}"
                            method="POST"
                            x-ref="subsForm"
                        >
                            @csrf
                            <template x-for="(sub, idx) in subs" :key="idx">
                                <div>
                                    <input type="hidden" :name="'substitutions[' + idx + '][out]'" :value="sub.out">
                                    <input type="hidden" :name="'substitutions[' + idx + '][in]'"  :value="sub.in">
                                </div>
                            </template>

                            <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-6 py-3.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                @if ($isHumanVsHuman)
                                    Confirmar Intervalo
                                @else
                                    Iniciar 2º Tempo
                                @endif
                            </button>
                        </form>
                    </div>
                    @elseif (! $canResume)
                        <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-6 py-8 text-center">
                            <p class="text-amber-400 font-semibold text-sm mb-1">Aguardando o intervalo terminar</p>
                            <p class="text-slate-500 text-xs">O técnico do time está preparando as substituições.</p>
                        </div>
                    @endif

                </div>

                {{-- Sidebar: stats do 1º tempo --}}
                <div class="space-y-4">
                    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Estatísticas — 1º Tempo</p>

                        {{-- Posse --}}
                        <div>
                            <div class="flex justify-between text-xs text-slate-400 mb-1">
                                <span class="font-bold text-white">{{ $homePossession }}%</span>
                                <span>Posse de bola</span>
                                <span class="font-bold text-white">{{ $awayPossession }}%</span>
                            </div>
                            <div class="flex h-2 rounded-full overflow-hidden bg-slate-800">
                                <div class="bg-emerald-500" style="width:{{ $homePossession }}%"></div>
                                <div class="bg-violet-500" style="width:{{ $awayPossession }}%"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-slate-600 mt-1">
                                <span>{{ $homeName }}</span>
                                <span>{{ $awayName }}</span>
                            </div>
                        </div>

                        {{-- Números --}}
                        <div class="space-y-2">
                            @foreach ([
                                ['label' => 'Chutes',  'home' => $homeShots,    'away' => $awayShots],
                                ['label' => 'No gol',  'home' => $homeOnTarget, 'away' => $awayOnTarget],
                                ['label' => 'Gols',    'home' => $homeScore,    'away' => $awayScore],
                            ] as $stat)
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-6 text-right font-bold tabular-nums text-white">{{ $stat['home'] }}</span>
                                    <div class="flex-1 text-center text-slate-500">{{ $stat['label'] }}</div>
                                    <span class="w-6 text-left font-bold tabular-nums text-white">{{ $stat['away'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Voltar à competição --}}
                    <a href="{{ route('competitions.show', [$league, $competition]) }}"
                       class="flex items-center justify-center gap-2 w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-slate-400 hover:text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                        Ver classificação
                    </a>
                </div>

            </div>
        </div>
        {{-- ════════════════════════════════════════════════════════════════ --}}

    </div>

</div>

@push('scripts')
<script>
/**
 * Componente Alpine para o painel de espera HvH.
 * Faz polling a cada 5s, conta regressiva e auto-submete o form de força ao chegar em 0.
 */
function hvhWaiting({ secondsLeft, statusUrl, resumeUrl, otherReady }) {
    return {
        secondsLeft,
        otherReady,
        statusUrl,
        resumeUrl,
        _countdownTimer: null,
        _pollTimer:      null,

        startPolling() {
            // Countdown: decrementa 1 por segundo
            this._countdownTimer = setInterval(() => {
                if (this.secondsLeft > 0) {
                    this.secondsLeft--;
                } else {
                    clearInterval(this._countdownTimer);
                    // Tempo esgotou — força o início do 2º tempo
                    this.$refs.forceForm.submit();
                }
            }, 1000);

            // Poll a cada 5s para detectar se o adversário confirmou
            this._pollTimer = setInterval(() => this.checkStatus(), 5000);
        },

        stopPolling() {
            clearInterval(this._countdownTimer);
            clearInterval(this._pollTimer);
        },

        async checkStatus() {
            try {
                const res  = await fetch(this.statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();

                if (data.finished) {
                    // Jogo já encerrado (outro jogador triggou) → vai pro replay
                    clearInterval(this._countdownTimer);
                    clearInterval(this._pollTimer);
                    window.location.href = data.match_url;
                    return;
                }

                if (data.home_ready && data.away_ready) {
                    // Ambos prontos — recarrega para disparar a simulação
                    clearInterval(this._countdownTimer);
                    clearInterval(this._pollTimer);
                    this.$refs.forceForm.submit();
                    return;
                }

                // Atualiza flags e contador
                this.otherReady  = data.away_ready || data.home_ready; // quem confirmou além de mim
                this.secondsLeft = data.seconds_left;

                if (data.can_force) {
                    clearInterval(this._countdownTimer);
                    clearInterval(this._pollTimer);
                    this.$refs.forceForm.submit();
                }
            } catch (_) {
                // Ignora erros de rede silenciosamente
            }
        },
    };
}

function halftimeReplay({ events, finalHomeScore, finalAwayScore }) {
    return {
        events,
        finalHomeScore,
        finalAwayScore,

        currentIndex:  -1,
        playing:       true,
        speed:         1,
        homeScore:     0,
        awayScore:     0,
        currentMinute: 1,
        narrations:    [],
        replayDone:    false,
        timer:         null,

        get progress() {
            if (this.events.length === 0) return 100;
            return Math.round((this.currentIndex + 1) / this.events.length * 100);
        },

        init() {
            if (this.events.length === 0) {
                this.replayDone = true;
                return;
            }
            setTimeout(() => this.tick(), 800);
        },

        tick() {
            if (!this.playing) return;

            if (this.currentIndex >= this.events.length - 1) {
                this.homeScore    = this.finalHomeScore;
                this.awayScore    = this.finalAwayScore;
                this.currentMinute = 45;
                this.replayDone   = true;
                return;
            }

            this.currentIndex++;
            const event = this.events[this.currentIndex];
            this.currentMinute = event.play;

            if (event.type === 'goal') {
                if (event.team === 'home') this.homeScore++;
                else this.awayScore++;
            }

            this.narrations.unshift(event);

            this.$nextTick(() => {
                const feed = document.getElementById('halftime-feed');
                if (feed) feed.scrollTop = 0;
            });

            // Se chegou no evento de intervalo, pausa um segundo extra antes de concluir
            const isLast = this.currentIndex >= this.events.length - 1;
            const delay  = isLast
                ? 1200
                : Math.max(50, Math.round(3600 / this.speed));

            this.timer = setTimeout(() => this.tick(), delay);
        },

        skip() {
            clearTimeout(this.timer);
            this.playing       = false;
            this.homeScore     = this.finalHomeScore;
            this.awayScore     = this.finalAwayScore;
            this.currentMinute = 45;
            this.replayDone    = true;
        },

        setSpeed(s) {
            const was = this.playing;
            if (was) clearTimeout(this.timer);
            this.speed = s;
            if (was && !this.replayDone) this.tick();
        },
    };
}
</script>
@endpush

</x-app-layout>
