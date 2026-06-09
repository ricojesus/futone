<x-app-layout>
    {{-- Flash messages --}}
    @if (session('success'))
        <div class="border-b border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400 text-center">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="border-b border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400 text-center">
            {{ session('error') }}
        </div>
    @endif
    @if (session('info'))
        <div class="border-b border-sky-500/20 bg-sky-500/10 px-4 py-3 text-sm text-sky-400 text-center">
            {{ session('info') }}
        </div>
    @endif

    {{-- Banner: temporada encerrada --}}
    @php
        $allCompetitionsFinished = $league->competitions->isNotEmpty()
            && $league->competitions->every(fn($c) => $c->status === \App\Models\Competition::STATUS_FINISHED);
    @endphp
    @if ($allCompetitionsFinished)
        <div class="sticky top-0 z-30 border-b border-yellow-500/30 bg-yellow-500/10 backdrop-blur-sm">
            <div class="mx-auto max-w-7xl px-4 py-2.5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-2.5 text-sm text-yellow-300">
                    <span class="text-base">🏆</span>
                    <span class="font-semibold">Temporada {{ $league->season }} encerrada!</span>
                    <span class="text-yellow-400/70 hidden sm:inline">Todas as competições foram finalizadas.</span>
                </div>
                <a href="{{ route('leagues.season-summary', $league) }}"
                   class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-yellow-500 px-4 py-1.5 text-xs font-bold text-slate-900 hover:bg-yellow-400 transition active:scale-95">
                    Ver resumo e avançar →
                </a>
            </div>
        </div>
    @endif

    {{-- Hero da liga --}}
    <div class="relative overflow-hidden border-b border-slate-800 bg-slate-900">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(16,185,129,0.08),transparent_60%)]"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <a href="{{ route('leagues.index') }}" class="text-sm text-slate-500 hover:text-slate-300 transition">Minhas Ligas</a>
                        <span class="text-slate-700">/</span>
                        <span class="text-sm text-slate-400">{{ $league->name }}</span>
                    </div>
                    <h1 class="text-2xl font-extrabold text-white sm:text-3xl">{{ $league->name }}</h1>

                    @if ($league->season)
                        <p class="mt-1 text-slate-400">{{ $league->seasonLabel() }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        {{-- Status --}}
                        @if ($league->isWaiting())
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                Aguardando
                            </span>
                        @elseif ($league->isInProgress())
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Em andamento
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-500/30 bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-400">
                                Encerrada
                            </span>
                        @endif

                        {{-- Visibilidade --}}
                        @if ($league->type === 'public')
                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-600 bg-slate-800 px-3 py-1 text-xs text-slate-400">
                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3" /></svg>
                                Pública
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 text-xs text-sky-400">
                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                Privada
                            </span>
                        @endif

                        {{-- Dono --}}
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-600 bg-slate-800 px-3 py-1 text-xs text-slate-400">
                            Criada por <strong class="text-white ml-1">{{ $league->owner->name }}</strong>
                        </span>

                        {{-- Tipo de atribuição --}}
                        @if ($league->isAutoAssignment())
                            <span class="inline-flex items-center gap-1 rounded-full border border-violet-500/30 bg-violet-500/10 px-3 py-1 text-xs text-violet-400">
                                🎲 Sorteio automático
                            </span>
                        @endif

                        {{-- Duração --}}
                        @if ($league->hasSeasonLimit())
                            <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs text-amber-400">
                                ⏱ {{ $league->max_seasons }} temporada(s)
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Ações principais --}}
                @php
                    $phaseLabels = [
                        'state'    => ['label' => 'Estaduais', 'icon' => '🏟', 'color' => 'blue'],
                        'copa'     => ['label' => 'Copa do Brasil', 'icon' => '🏆', 'color' => 'amber'],
                        'national' => ['label' => 'Brasileirão', 'icon' => '🇧🇷', 'color' => 'emerald'],
                    ];
                    $currentPhaseInfo = $phaseLabels[$league->current_phase] ?? null;
                @endphp
                <div class="flex shrink-0 flex-col gap-2 sm:items-end">

                    {{-- Badge de fase atual --}}
                    @if ($league->isInProgress() && $currentPhaseInfo)
                        <div class="inline-flex items-center gap-1.5 rounded-full border border-slate-600 bg-slate-800 px-3 py-1 text-xs text-slate-300">
                            <span>{{ $currentPhaseInfo['icon'] }}</span>
                            <span class="font-semibold">{{ $currentPhaseInfo['label'] }}</span>
                        </div>
                    @endif

                    @if ($isOwner && $league->competitions->isEmpty())
                        <form action="{{ route('leagues.generate', $league) }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95"
                                onclick="return confirm('Gerar os campeonatos desta temporada agora?')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /></svg>
                                Gerar Competições
                            </button>
                        </form>
                    @elseif ($isOwner && $league->isWaiting())
                        <form action="{{ route('leagues.start', $league) }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-violet-500 px-5 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-violet-400 active:scale-95"
                                onclick="return confirm('Iniciar a liga agora?')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
                                Iniciar Liga
                            </button>
                        </form>
                    @elseif ($isOwner && $league->isInProgress() && !$allCompetitionsFinished)
                        {{-- Botão principal: Avançar semana --}}
                        <form action="{{ route('leagues.advance-week', $league) }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 text-sm font-bold uppercase tracking-wider text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400 active:scale-95">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z" />
                                </svg>
                                Avançar Semana
                            </button>
                        </form>
                    @elseif (! $isOwner && $league->isInProgress() && ! $allCompetitionsFinished)
                        {{-- Mensagem para jogadores não-donos --}}
                        <div class="inline-flex items-center gap-2.5 rounded-xl border border-slate-700 bg-slate-800/60 px-5 py-3">
                            <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                            <div class="text-left">
                                <p class="text-xs font-semibold text-slate-300">Aguardando próxima rodada</p>
                                <p class="text-[10px] text-slate-500 mt-0.5">O dono da liga controla o avanço das rodadas.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

        {{-- Código de convite --}}
        @if ($league->invite_code && $isOwner)
            <div class="mb-8 rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5" x-data="{ copied: false }">
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-sky-400">Código de Convite</p>
                <div class="flex items-center gap-2 max-w-xs">
                    <code class="flex-1 rounded-lg bg-slate-800 px-4 py-2.5 text-center text-lg font-bold tracking-[0.3em] text-white border border-slate-700">
                        {{ $league->invite_code }}
                    </code>
                    <button @click="navigator.clipboard.writeText('{{ $league->invite_code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="shrink-0 rounded-lg border border-slate-700 bg-slate-800 p-2.5 text-slate-400 hover:text-white transition">
                        <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                        <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </button>
                </div>
                <p class="mt-2 text-xs text-sky-400/70">Compartilhe este código para convidar jogadores para esta liga.</p>
            </div>
        @endif

        {{-- ══ LOBBY: sorteio automático ══════════════════════════════════════ --}}
        @if ($league->isAutoAssignment() && $league->isWaiting())
            @php
                $members        = $league->members()->with('user')->orderBy('created_at')->get();
                $waitingMembers = $members->where('status', 'waiting');
                $myMembership   = $members->firstWhere('user_id', auth()->id());
                $myLeagueTeam   = $league->leagueTeams->firstWhere('user_id', auth()->id());
                $alreadyInLobby = $myMembership !== null || $myLeagueTeam !== null || $isOwner;
            @endphp
            <div class="mb-8 rounded-2xl border border-violet-500/20 bg-violet-500/5 overflow-hidden">
                {{-- Header do lobby --}}
                <div class="flex items-center justify-between border-b border-violet-500/10 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🎲</span>
                        <div>
                            <h2 class="font-bold text-white text-sm">Lobby — Aguardando Sorteio</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ $waitingMembers->count() }} jogador(es) na fila · O dono sorteia os times quando todos estiverem prontos
                            </p>
                        </div>
                    </div>

                    {{-- Ações --}}
                    @if ($isOwner)
                        @if ($waitingMembers->isNotEmpty())
                            <form action="{{ route('leagues.lobby.draw', $league) }}" method="POST">
                                @csrf
                                <button type="submit"
                                    onclick="return confirm('Sortear os times para todos na fila agora?')"
                                    class="inline-flex items-center gap-2 rounded-xl bg-violet-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-violet-400 active:scale-95">
                                    🎲 Sortear Times
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-slate-600 italic">Nenhum jogador na fila ainda</span>
                        @endif
                    @elseif (! $alreadyInLobby)
                        <form action="{{ route('leagues.lobby.join', $league) }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-violet-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-violet-400 active:scale-95">
                                + Entrar na fila
                            </button>
                        </form>
                    @elseif ($myMembership?->isWaiting())
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-500/40 bg-violet-500/10 px-3 py-1.5 text-xs font-semibold text-violet-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-violet-400 animate-pulse"></span>
                            Na fila
                        </span>
                    @elseif ($myMembership?->status === 'assigned' || $myLeagueTeam)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-400">
                            ✓ Time sorteado
                        </span>
                    @endif
                </div>

                {{-- Lista de membros --}}
                @if ($members->isEmpty())
                    <div class="px-5 py-8 text-center">
                        <p class="text-slate-600 text-sm">Nenhum jogador na fila ainda.</p>
                        <p class="text-xs text-slate-600 mt-1">Compartilhe o código de convite para que os amigos entrem.</p>
                    </div>
                @else
                    <div class="divide-y divide-violet-500/10">
                        @foreach ($members as $member)
                            @php
                                $memberTeam = $league->leagueTeams->firstWhere('user_id', $member->user_id);
                            @endphp
                            <div class="flex items-center gap-3 px-5 py-3">
                                {{-- Avatar inicial --}}
                                <div class="shrink-0 h-8 w-8 rounded-full bg-slate-700 flex items-center justify-center text-sm font-bold text-slate-300">
                                    {{ strtoupper(substr($member->user->name ?? '?', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white truncate">
                                        {{ $member->user->name }}
                                        @if ($member->user_id === $league->owner_id)
                                            <span class="ml-1 text-[10px] text-slate-500">(dono)</span>
                                        @endif
                                    </p>
                                    @if ($memberTeam)
                                        <p class="text-xs text-emerald-400 truncate">⚽ {{ $memberTeam->name }}</p>
                                    @else
                                        <p class="text-xs text-slate-600">Aguardando sorteio…</p>
                                    @endif
                                </div>
                                {{-- Status badge --}}
                                @if ($member->status === 'assigned' || $memberTeam)
                                    <span class="shrink-0 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-400">Sorteado</span>
                                @else
                                    <span class="shrink-0 rounded-full border border-violet-500/30 bg-violet-500/10 px-2 py-0.5 text-[10px] font-semibold text-violet-400">Na fila</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
        {{-- ════════════════════════════════════════════════════════════════════ --}}

        {{-- Competições --}}
        <div>
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">
                Competições
                <span class="ml-1 font-normal text-slate-500">({{ $league->competitions->count() }})</span>
            </h2>

            @if ($league->competitions->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-8 py-12 text-center">
                    <p class="text-slate-500">Nenhuma competição nesta liga ainda.</p>
                    <p class="text-sm text-slate-600 mt-1">Use o comando <code class="bg-slate-800 px-1 rounded">leagues:generate</code> para gerar as competições.</p>
                </div>
            @else
                @php
                    // Verifica se o usuário já tem LeagueTeam nesta liga
                    $myLeagueTeamInLeague = $league->leagueTeams->firstWhere('user_id', auth()->id());
                    $alreadyEnrolled = $myLeagueTeamInLeague !== null;
                @endphp

                {{-- ── Card de satisfação do clube (só para técnicos humanos em jogo) ── --}}
                @if ($myLeagueTeamInLeague && $league->isInProgress())
                    @php
                        $sat       = $myLeagueTeamInLeague->satisfaction;
                        $threshold = $myLeagueTeamInLeague->firingThreshold();
                        $margin    = $sat - $threshold;

                        [$barColor, $cardBorder, $cardBg, $statusColor, $statusLabel] = match(true) {
                            $margin >= 20 => ['bg-emerald-500', 'border-emerald-500/20', 'bg-emerald-500/5',  'text-emerald-400', '✓ Cargo seguro'],
                            $margin >= 5  => ['bg-amber-400',   'border-amber-500/20',   'bg-amber-500/5',    'text-amber-400',   '⚠ Atenção'],
                            $margin >= 0  => ['bg-orange-500',  'border-orange-500/20',  'bg-orange-500/5',   'text-orange-400',  '⚠ Em risco'],
                            default       => ['bg-red-500',     'border-red-500/30',     'bg-red-500/5',      'text-red-400',     '⛔ Demissão iminente'],
                        };
                    @endphp

                    <div class="mb-6 rounded-2xl border {{ $cardBorder }} {{ $cardBg }} px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-4">

                            {{-- Identificação --}}
                            <div class="flex items-center gap-3">
                                <x-team-badge :team="$myLeagueTeamInLeague" size="md" />
                                <div>
                                    <p class="text-sm font-bold text-white leading-tight">{{ $myLeagueTeamInLeague->name }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Satisfação do clube com você</p>
                                </div>
                            </div>

                            {{-- Barra + status --}}
                            <div class="flex items-center gap-4 min-w-[220px] flex-1 justify-end">
                                <div class="flex-1 max-w-[180px]">
                                    {{-- Labels --}}
                                    <div class="flex justify-between text-[10px] text-slate-500 mb-1.5">
                                        <span class="font-bold {{ $statusColor }}">{{ $sat }}/100</span>
                                        <span>limiar {{ $threshold }}</span>
                                    </div>
                                    {{-- Barra --}}
                                    <div class="relative h-2 rounded-full bg-slate-700/80">
                                        <div class="{{ $barColor }} h-full rounded-full transition-all duration-500"
                                             style="width:{{ $sat }}%"></div>
                                        {{-- Marcador do limiar --}}
                                        <div class="absolute top-1/2 -translate-y-1/2 w-0.5 h-4 rounded-full bg-white/30"
                                             style="left:{{ $threshold }}%"></div>
                                    </div>
                                </div>
                                <span class="shrink-0 text-xs font-semibold {{ $statusColor }}">{{ $statusLabel }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── Atalhos do técnico ──────────────────────────────────────── --}}
                @if ($myLeagueTeamInLeague && $league->isInProgress())
                    <div class="mb-6 flex flex-wrap gap-2">
                        <a href="{{ route('leagues.teams.show', [$league, $myLeagueTeamInLeague]) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:border-slate-600 hover:text-white transition">
                            Meu Time
                        </a>
                        <a href="{{ route('leagues.lineup.edit', [$league, $myLeagueTeamInLeague]) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:border-slate-600 hover:text-white transition">
                            Escalação
                        </a>
                        <a href="{{ route('leagues.transfers.index', $league) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-xs font-semibold text-emerald-400 hover:bg-emerald-500/20 transition">
                            Mercado de Transferências
                        </a>
                        <a href="{{ route('leagues.transfers.offers', $league) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:border-slate-600 hover:text-white transition">
                            Minhas Propostas
                        </a>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($league->competitions as $competition)
                        @php
                            // Check if the user's LeagueTeam has a CompetitionTeam in this competition
                            $myTeamInComp = $myLeagueTeamInLeague
                                ? $competition->teams->firstWhere('league_team_id', $myLeagueTeamInLeague->id)
                                : null;
                        @endphp
                        <div class="flex flex-col rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-slate-600">

                            {{-- Topo: nome + status --}}
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="font-bold text-white leading-snug">{{ $competition->name }}</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ $competition->divisionLabel() }}
                                        @if ($competition->isStateChampionship() && $competition->state)
                                            · {{ $competition->state->code }}
                                        @elseif ($competition->isNationalChampionship())
                                            · Nacional
                                        @endif
                                    </p>
                                </div>
                                @if ($competition->isWaiting())
                                    <span class="shrink-0 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-400">Aguardando</span>
                                @elseif ($competition->isInProgress())
                                    <span class="shrink-0 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-400">
                                        Rod. {{ $competition->current_round }}/{{ $competition->total_rounds }}
                                    </span>
                                @else
                                    <span class="shrink-0 rounded-full border border-slate-600 bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-400">Encerrada</span>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="flex items-center gap-3 text-xs text-slate-500 mb-4">
                                <span>{{ $competition->teams_count }} times</span>
                                @if ($competition->total_rounds)
                                    <span>·</span>
                                    <span>{{ $competition->total_rounds }} rodadas</span>
                                @endif
                            </div>

                            {{-- Time do usuário ou ação --}}
                            <div class="mt-auto flex items-center justify-between gap-2 border-t border-slate-800 pt-3">
                                @if ($myTeamInComp)
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <x-team-badge :team="$myLeagueTeamInLeague" size="xs" />
                                        <span class="text-xs text-emerald-400 font-medium truncate">{{ $myTeamInComp->name }}</span>
                                    </div>
                                @elseif (! $alreadyEnrolled && ! $competition->isFinished())
                                    <a href="{{ route('competitions.join', [$league, $competition]) }}"
                                        class="text-xs font-semibold text-emerald-400 hover:text-emerald-300 transition">
                                        + Escolher time
                                    </a>
                                @else
                                    <span class="text-xs text-slate-600">Sem time</span>
                                @endif

                                <a href="{{ route('competitions.show', [$league, $competition]) }}"
                                    class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 hover:border-slate-600 hover:text-white transition">
                                    Ver agenda
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
