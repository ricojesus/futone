@php
    $events         = $match->data['events']              ?? [];
    $homeFormation  = $match->data['home_formation']       ?? '4-4-2';
    $awayFormation  = $match->data['away_formation']       ?? '4-4-2';
    $homePossession = $match->data['home_possession']      ?? 50;
    $awayPossession = $match->data['away_possession']      ?? 50;
    $homeShots      = $match->data['home_shots']           ?? 0;
    $awayShots      = $match->data['away_shots']           ?? 0;
    $homeOnTarget   = $match->data['home_shots_on_target'] ?? 0;
    $awayOnTarget   = $match->data['away_shots_on_target'] ?? 0;
    $finalHome      = $match->home_score;
    $finalAway      = $match->away_score;
    $homeName       = $match->homeTeam->name;
    $awayName       = $match->awayTeam->name;
    $myHome         = $isMyMatch && $side === 'home';
    $myAway         = $isMyMatch && $side === 'away';

    // Eventos de gol extraídos para exibição de artilheiros
    $goalEvents = collect($events)->filter(fn($e) => ($e['type'] ?? '') === 'goal');

    // Replay do 2º tempo: começa do minuto 46
    $replayEvents = isset($secondHalf) && $secondHalf
        ? collect($events)->filter(fn($e) => ($e['play'] ?? 0) >= 46)->values()->all()
        : $events;

    $replayInitialHome = isset($halftimeHomeScore) ? $halftimeHomeScore : 0;
    $replayInitialAway = isset($halftimeAwayScore) ? $halftimeAwayScore : 0;
@endphp

<x-app-layout>
<div class="min-h-screen bg-slate-950"
    @if ($replayMode)
        x-data="matchReplay({
            events:           {{ Js::from($replayEvents) }},
            finalHomeScore:   {{ $finalHome }},
            finalAwayScore:   {{ $finalAway }},
            initialHomeScore: {{ $replayInitialHome }},
            initialAwayScore: {{ $replayInitialAway }},
        })"
        x-init="init()"
    @endif
>

    {{-- ── Top bar ─────────────────────────────────────────────────────── --}}
    <div class="sticky top-0 z-20 border-b border-slate-800 bg-slate-900/90 backdrop-blur-sm">
        <div class="mx-auto max-w-5xl px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <a href="{{ route('leagues.show', $league) }}" class="hover:text-slate-300 transition">{{ $league->name }}</a>
                <span class="text-slate-700">/</span>
                <a href="{{ route('competitions.show', [$league, $competition]) }}" class="hover:text-slate-300 transition">{{ $competition->name }}</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400">Rodada {{ $match->round }}</span>
            </div>
            @if ($replayMode)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Ao vivo
                </span>
            @else
                <a href="{{ route('matches.show', [$league, $competition, $match, 'replay' => 1]) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-slate-400 hover:text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    Rever partida
                </a>
            @endif
        </div>
    </div>

    {{-- ── Scoreboard ──────────────────────────────────────────────────── --}}
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
                    <p class="text-xs text-slate-500">{{ $homeFormation }} · Casa</p>
                    @if ($myHome)
                        <span class="inline-block rounded-full bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5 text-[10px] font-semibold text-emerald-400">Seu time</span>
                    @endif
                </div>

                {{-- Score --}}
                <div class="text-center">
                    <div class="flex items-center justify-center gap-4">
                        @if ($replayMode)
                            <span class="text-5xl font-black tabular-nums text-white" x-text="homeScore">0</span>
                            <span class="text-2xl text-slate-600">×</span>
                            <span class="text-5xl font-black tabular-nums text-white" x-text="awayScore">0</span>
                        @else
                            <span class="text-5xl font-black tabular-nums text-white">{{ $finalHome }}</span>
                            <span class="text-2xl text-slate-600">×</span>
                            <span class="text-5xl font-black tabular-nums text-white">{{ $finalAway }}</span>
                        @endif
                    </div>
                    <div class="mt-2 flex items-center justify-center gap-2">
                        @if ($replayMode)
                            <span class="h-2 w-2 rounded-full bg-emerald-400" :class="playing ? 'animate-pulse' : 'opacity-30'"></span>
                            <span class="text-sm font-bold tabular-nums"
                                  :class="finished ? 'text-slate-400' : 'text-emerald-400'"
                                  x-text="finished ? 'ENCERRADO' : currentMinute + '\''">0'</span>
                        @else
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Encerrado · 90'</span>
                        @endif
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
                    <p class="text-xs text-slate-500">{{ $awayFormation }} · Fora</p>
                    @if ($myAway)
                        <span class="inline-block rounded-full bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5 text-[10px] font-semibold text-emerald-400">Seu time</span>
                    @endif
                </div>
            </div>

            {{-- Progress bar (replay mode only) --}}
            @if ($replayMode)
                <div class="mt-5 h-1 rounded-full bg-slate-800 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                         :style="'width:' + progress + '%'"></div>
                </div>

                {{-- Controls --}}
                <div class="mt-4 flex items-center justify-center gap-3 flex-wrap">

                    <div x-show="finished"
                         class="inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-bold bg-slate-700 text-slate-400">
                        Encerrado
                    </div>
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
            @endif
        </div>
    </div>

    {{-- ── Main body ───────────────────────────────────────────────────── --}}
    <div class="mx-auto max-w-5xl px-4 py-6">
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Coluna principal --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- ── Gols (modo estático) ─────────────────────────────── --}}
                @if (! $replayMode)
                    <div>
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">Gols</h2>

                        @if ($goalEvents->isEmpty())
                            <div class="rounded-2xl border border-dashed border-slate-800 px-6 py-6 text-center">
                                <p class="text-slate-600 text-sm">Nenhum gol nesta partida.</p>
                            </div>
                        @else
                            <div class="rounded-2xl border border-slate-800 bg-slate-900 divide-y divide-slate-800 overflow-hidden">
                                @foreach ($goalEvents as $goal)
                                    @php
                                        $isHome = ($goal['team'] ?? '') === 'home';
                                    @endphp
                                    <div class="flex items-center gap-4 px-5 py-3.5">
                                        {{-- Minuto --}}
                                        <span class="shrink-0 text-sm font-bold tabular-nums text-emerald-400 w-10">
                                            {{ $goal['play'] ?? '—' }}'
                                        </span>

                                        {{-- Time + goleador --}}
                                        @if ($isHome)
                                            <div class="flex-1">
                                                <p class="font-semibold text-white leading-tight">{{ $homeName }}</p>
                                                @if (! empty($goal['scorer_name']))
                                                    <p class="text-xs text-emerald-400/80">{{ $goal['scorer_name'] }}</p>
                                                @endif
                                            </div>
                                            <span class="text-slate-700 text-lg">⚽</span>
                                            <span class="flex-1 text-right text-slate-600 text-sm">{{ $awayName }}</span>
                                        @else
                                            <span class="flex-1 text-slate-600 text-sm">{{ $homeName }}</span>
                                            <span class="text-slate-700 text-lg">⚽</span>
                                            <div class="flex-1 text-right">
                                                <p class="font-semibold text-white leading-tight">{{ $awayName }}</p>
                                                @if (! empty($goal['scorer_name']))
                                                    <p class="text-xs text-emerald-400/80">{{ $goal['scorer_name'] }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    {{-- Narração do gol --}}
                                    @if (! empty($goal['narration']))
                                        <div class="px-5 pb-3 text-xs text-slate-500 italic border-t-0">
                                            "{{ $goal['narration'] }}"
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- ── Narração completa (acordeão) ───────────────────── --}}
                    <div x-data="{ open: false }">
                        <button @click="open = !open"
                            class="mb-3 flex w-full items-center justify-between text-xs font-semibold uppercase tracking-widest text-slate-500 hover:text-slate-300 transition">
                            <span>Narração completa</span>
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

                @else
                    {{-- ── Feed de narração animado (replay mode) ─────────── --}}
                    <div>
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">
                            Narração
                            @if (isset($secondHalf) && $secondHalf)
                                <span class="ml-2 font-normal normal-case text-violet-400">— 2º Tempo</span>
                            @endif
                        </h2>
                        <div class="space-y-2 max-h-[55vh] overflow-y-auto pr-1" id="narration-feed">
                            <template x-if="narrations.length === 0 && !playing">
                                <div class="rounded-2xl border border-dashed border-slate-800 px-6 py-10 text-center">
                                    <p class="text-slate-600 text-sm">Pressione ▶ para iniciar {{ isset($secondHalf) && $secondHalf ? 'o 2º tempo' : 'a partida' }}</p>
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
                @endif
            </div>

            {{-- ── Sidebar ─────────────────────────────────────────────── --}}
            <div class="space-y-4">

                {{-- Estatísticas --}}
                @php $showStats = ! $replayMode; @endphp
                <div @if ($replayMode) x-show="finished" x-transition @endif
                     class="rounded-2xl border border-slate-800 bg-slate-900 p-4 space-y-4"
                     @if ($replayMode && ! $showStats) style="display:none" @endif>

                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Estatísticas</p>

                    {{-- Posse --}}
                    <div>
                        <div class="flex justify-between text-xs text-slate-400 mb-1">
                            <span class="font-bold text-white">{{ $homePossession }}%</span>
                            <span>Posse de bola</span>
                            <span class="font-bold text-white">{{ $awayPossession }}%</span>
                        </div>
                        <div class="flex h-2 rounded-full overflow-hidden bg-slate-800">
                            <div class="bg-emerald-500 transition-all" style="width:{{ $homePossession }}%"></div>
                            <div class="bg-violet-500 transition-all" style="width:{{ $awayPossession }}%"></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-600 mt-1">
                            <span>{{ $homeName }}</span>
                            <span>{{ $awayName }}</span>
                        </div>
                    </div>

                    {{-- Números --}}
                    <div class="space-y-2">
                        @foreach ([
                            ['label' => 'Chutes',          'home' => $homeShots,     'away' => $awayShots],
                            ['label' => 'No gol',          'home' => $homeOnTarget,  'away' => $awayOnTarget],
                            ['label' => 'Formação',        'home' => $homeFormation, 'away' => $awayFormation],
                        ] as $stat)
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-white w-10 text-left">{{ $stat['home'] }}</span>
                                <span class="text-slate-500">{{ $stat['label'] }}</span>
                                <span class="font-bold text-white w-10 text-right">{{ $stat['away'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Outros jogos da rodada --}}
                <div @if ($replayMode) x-show="finished" x-transition @endif
                     class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">
                        Rodada {{ $match->round }}
                    </p>
                    <div class="space-y-2">
                        @foreach ($roundMatches as $rm)
                            @php $isThis = $rm->id === $match->id; @endphp
                            <div class="flex items-center gap-2 text-xs {{ $isThis ? 'text-white font-semibold' : 'text-slate-500' }}">
                                <span class="flex-1 text-right truncate">{{ $rm->homeTeam->name }}</span>
                                <a @if($rm->status === 'finished') href="{{ route('matches.show', [$league, $competition, $rm]) }}" @endif
                                   class="shrink-0 w-14 text-center tabular-nums {{ $rm->status === 'finished' ? 'hover:text-emerald-400 transition' : 'text-slate-700' }}">
                                    @if ($rm->status === 'finished')
                                        {{ $rm->home_score }} × {{ $rm->away_score }}
                                    @else
                                        vs
                                    @endif
                                </a>
                                <span class="flex-1 truncate">{{ $rm->awayTeam->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Ações --}}
                <div @if ($replayMode) x-show="finished" x-transition @endif
                     class="space-y-2"
                     @if ($replayMode) style="display:none" @endif>
                    @if ($isMyMatch && $myLeagueTeam)
                        <a href="{{ route('leagues.lineup.edit', [$league, $myLeagueTeam]) }}"
                            class="flex items-center justify-center gap-2 w-full rounded-xl border border-violet-500/40 bg-violet-500/10 px-4 py-2.5 text-sm font-semibold text-violet-400 hover:bg-violet-500/20 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                            Escalação próxima rodada
                        </a>
                    @endif
                    <a href="{{ route('competitions.show', [$league, $competition]) }}"
                        class="flex items-center justify-center gap-2 w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-slate-300 hover:text-white transition">
                        Ver classificação →
                    </a>
                </div>

            </div>{{-- /sidebar --}}
        </div>
    </div>
</div>

@if ($replayMode)
@push('scripts')
<script>
function matchReplay({ events, finalHomeScore, finalAwayScore, initialHomeScore = 0, initialAwayScore = 0 }) {
    return {
        events,
        finalHomeScore,
        finalAwayScore,

        currentIndex:  -1,
        playing:       false,
        speed:         1,
        homeScore:     initialHomeScore,
        awayScore:     initialAwayScore,
        currentMinute: events.length > 0 ? events[0].play : 1,
        narrations:    [],
        finished:      false,
        timer:         null,

        get progress() {
            return this.events.length === 0 ? 0
                : Math.round((this.currentIndex + 1) / this.events.length * 100);
        },

        init() { setTimeout(() => this.play(), 1000); },

        play() {
            if (this.finished) return;
            this.playing = true;
            this.tick();
        },

        pause() {
            this.playing = false;
            clearTimeout(this.timer);
        },

        restart() {
            this.pause();
            this.currentIndex  = -1;
            this.homeScore     = initialHomeScore;
            this.awayScore     = initialAwayScore;
            this.currentMinute = this.events.length > 0 ? this.events[0].play : 1;
            this.narrations    = [];
            this.finished      = false;
            setTimeout(() => this.play(), 500);
        },

        setSpeed(s) {
            const was = this.playing;
            if (was) this.pause();
            this.speed = s;
            if (was && !this.finished) this.play();
        },

        tick() {
            if (!this.playing) return;

            if (this.currentIndex >= this.events.length - 1) {
                this.finished      = true;
                this.playing       = false;
                this.currentMinute = 90;
                this.homeScore     = this.finalHomeScore;
                this.awayScore     = this.finalAwayScore;
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
                const feed = document.getElementById('narration-feed');
                if (feed) feed.scrollTop = 0;
            });

            const delay = Math.max(50, Math.round(3600 / this.speed));
            this.timer = setTimeout(() => this.tick(), delay);
        },
    };
}
</script>
@endpush
@endif
</x-app-layout>
