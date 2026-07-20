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

    @php
        $allCompetitionsFinished = $league->competitions->isNotEmpty()
            && $league->competitions->every(fn($c) => $c->status === \App\Models\Competition::STATUS_FINISHED);
    @endphp

    {{-- Hero do Escritório --}}
    <div class="relative overflow-hidden border-b border-slate-800 bg-slate-900">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(16,185,129,0.08),transparent_60%)]"></div>
        <div class="relative mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <a href="{{ route('leagues.index') }}" class="text-sm text-slate-500 hover:text-slate-300 transition">Minhas Ligas</a>
                <span class="text-slate-700">/</span>
                <a href="{{ route('leagues.show', ['league' => $league, 'classic' => 1]) }}" class="text-sm text-slate-500 hover:text-slate-300 transition">{{ $league->name }}</a>
                <span class="text-slate-700">/</span>
                <span class="text-sm text-slate-400">Escritório</span>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    @if ($myTeam)
                        <x-team-badge :team="$myTeam" size="lg" />
                    @endif
                    <div>
                        <h1 class="text-2xl font-extrabold text-white sm:text-3xl">🗄️ Escritório do Técnico</h1>
                        <p class="mt-1 text-slate-400">
                            @if ($myTeam)
                                Comandando o <strong class="text-emerald-400">{{ $myTeam->name }}</strong> · {{ $league->seasonLabel() }}
                            @elseif ($isFired)
                                <span class="text-amber-400">Sem clube no momento</span> — aguardando convites · {{ $league->seasonLabel() }}
                            @else
                                {{ $league->seasonLabel() }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('leagues.show', ['league' => $league, 'classic' => 1]) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:border-slate-600 hover:text-white transition">
                        Página da Liga
                    </a>
                    @if ($myTeam)
                        <a href="{{ route('leagues.teams.show', [$league, $myTeam]) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:border-slate-600 hover:text-white transition">
                            Meu Time
                        </a>
                        <a href="{{ route('leagues.lineup.edit', [$league, $myTeam]) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:border-slate-600 hover:text-white transition">
                            Escalação
                        </a>
                        <a href="{{ route('leagues.transfers.index', $league) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-xs font-semibold text-emerald-400 hover:bg-emerald-500/20 transition">
                            Mercado
                        </a>
                    @endif
                    @if ($isOwner && $league->isInProgress() && ! $allCompetitionsFinished)
                        <form action="{{ route('leagues.advance-week', $league) }}" method="POST" class="inline-flex">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400 active:scale-95">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z" />
                                </svg>
                                Avançar Rodada
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">

        {{-- ── Situação do clube (satisfação do técnico + torcida + finanças) ─────── --}}
        @if ($myTeam)
            @php
                $sat       = $myTeam->coach_satisfaction;
                $threshold = $myTeam->firingThreshold();
                $margin    = $sat - $threshold;

                [$barColor, $cardBorder, $cardBg, $statusColor, $statusLabel] = match(true) {
                    $margin >= 20 => ['bg-emerald-500', 'border-emerald-500/20', 'bg-emerald-500/5',  'text-emerald-400', '✓ Cargo seguro'],
                    $margin >= 5  => ['bg-amber-400',   'border-amber-500/20',   'bg-amber-500/5',    'text-amber-400',   '⚠ Atenção'],
                    $margin >= 0  => ['bg-orange-500',  'border-orange-500/20',  'bg-orange-500/5',   'text-orange-400',  '⚠ Em risco'],
                    default       => ['bg-red-500',     'border-red-500/30',     'bg-red-500/5',      'text-red-400',     '⛔ Demissão iminente'],
                };

                $torcida = $myTeam->satisfaction;
                $budget  = $myTeam->budget;
            @endphp

            <section>
                <h2 class="mb-3 text-lg font-bold text-white">📊 Situação do Clube</h2>

                {{-- Satisfação do clube com o técnico (risco de demissão) --}}
                <div class="mb-4 rounded-2xl border {{ $cardBorder }} {{ $cardBg }} px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-white leading-tight">Satisfação do Clube com Você</p>
                            <p class="text-xs text-slate-500 mt-0.5">Quanto a diretoria do {{ $myTeam->name }} confia no seu trabalho</p>
                        </div>
                        <div class="flex items-center gap-4 min-w-[220px] flex-1 justify-end">
                            <div class="flex-1 max-w-[180px]">
                                <div class="flex justify-between text-[10px] text-slate-500 mb-1.5">
                                    <span class="font-bold {{ $statusColor }}">{{ $sat }}/100</span>
                                    <span>limiar {{ $threshold }}</span>
                                </div>
                                <div class="relative h-2 rounded-full bg-slate-700/80">
                                    <div class="{{ $barColor }} h-full rounded-full transition-all duration-500"
                                         style="width:{{ $sat }}%"></div>
                                    <div class="absolute top-1/2 -translate-y-1/2 w-0.5 h-4 rounded-full bg-white/30"
                                         style="left:{{ $threshold }}%"></div>
                                </div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold {{ $statusColor }}">{{ $statusLabel }}</span>
                        </div>
                    </div>
                </div>

                {{-- Condição financeira + satisfação da torcida (influencia bilheteria) --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1">Satisfação da Torcida</p>
                        <p class="text-xl font-bold mb-2 {{ $torcida >= 60 ? 'text-emerald-400' : ($torcida >= 35 ? 'text-yellow-400' : 'text-red-400') }}">
                            {{ $torcida }}/100
                        </p>
                        <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                            <div class="h-2 rounded-full {{ $torcida >= 60 ? 'bg-emerald-500' : ($torcida >= 35 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                 style="width: {{ $torcida }}%"></div>
                        </div>
                        <p class="text-xs text-slate-600 mt-1">Influencia o público nos jogos</p>
                    </div>
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1">Saldo</p>
                        <p class="text-xl font-bold {{ $budget >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            R$ {{ number_format(abs($budget), 0, ',', '.') }}
                            {{ $budget < 0 ? '(negativo)' : '' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1">Folha Salarial / Semana</p>
                        <p class="text-xl font-bold text-slate-200">
                            R$ {{ number_format($weeklyWage, 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1">Preço do Ingresso</p>
                        <form method="POST" action="{{ route('leagues.teams.ticket-price', [$league, $myTeam]) }}" class="mt-1 flex items-center gap-2">
                            @csrf @method('PATCH')
                            <span class="text-slate-400 text-sm">R$</span>
                            <input type="number" name="ticket_price" min="10" max="500" title="Mín. R$10 · Máx. R$500"
                                   value="{{ old('ticket_price', $myTeam->ticket_price) }}"
                                   class="w-16 rounded-lg bg-slate-800 border border-slate-600 px-2 py-1 text-sm text-white focus:border-emerald-500 focus:outline-none">
                            <button type="submit"
                                    class="rounded-lg bg-emerald-600 hover:bg-emerald-500 px-3 py-1 text-xs font-semibold text-white transition">
                                Salvar
                            </button>
                        </form>
                        @error('ticket_price')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($budget < 0)
                    <div class="mt-4 flex items-center gap-3 rounded-2xl border border-red-500/30 bg-red-500/10 px-5 py-3 text-sm text-red-300">
                        <span>⚠️</span>
                        <span><strong>Saldo negativo.</strong> As finanças do clube estão no vermelho — venda jogadores, ajuste o ingresso ou reduza a folha salarial.</span>
                    </div>
                @endif
            </section>
        @endif

        {{-- ── Convites (usuário demitido) ─────────────────────────────── --}}
        @if ($isFired)
            <section>
                <h2 class="mb-3 text-lg font-bold text-white">📨 Convites de clubes</h2>

                @if ($invitations->isEmpty())
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6 text-center text-sm text-slate-400">
                        Nenhum convite nesta rodada. Novos clubes podem procurá-lo quando a liga avançar a semana.
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($invitations as $invitation)
                            @php $club = $invitation->leagueTeam; @endphp
                            <div class="flex flex-col rounded-2xl border border-emerald-500/30 bg-slate-900 p-5">
                                <div class="mb-3">
                                    <h3 class="font-bold text-white leading-snug">{{ $club->name }}</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        @if ($club->national_division === 'first')
                                            Série A
                                        @elseif ($club->national_division === 'second')
                                            Série B
                                        @else
                                            Sem divisão nacional
                                        @endif
                                        · Estádio para {{ number_format($club->stadium_capacity, 0, ',', '.') }}
                                    </p>
                                </div>
                                <a href="{{ route('leagues.teams.show', [$league, $club]) }}"
                                   class="mb-2 inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:border-slate-600 hover:text-white transition">
                                    🔎 Ver jogadores, finanças e classificação
                                </a>
                                <div class="mt-auto flex gap-2">
                                    <form method="POST" action="{{ route('leagues.office.invitations.accept', [$league, $invitation]) }}" class="flex-1">
                                        @csrf
                                        <button type="submit"
                                                class="w-full rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold text-slate-900 hover:bg-emerald-400 transition active:scale-95">
                                            Assumir clube
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('leagues.office.invitations.decline', [$league, $invitation]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white transition">
                                            Recusar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        {{-- ── Caixa de mensagens ──────────────────────────────────────── --}}
        <section x-data="{ filter: 'all' }">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-white">
                    ✉️ Mensagens
                    @if ($unreadCount > 0)
                        <span class="ml-2 inline-flex items-center rounded-full bg-emerald-500 px-2 py-0.5 text-xs font-bold text-slate-900">{{ $unreadCount }} nova(s)</span>
                    @endif
                </h2>

                {{-- Filtro por tipo --}}
                @php
                    $types = [
                        'all'        => 'Todas',
                        'club'       => '🏛 Clube',
                        'financial'  => '💰 Financeiro',
                        'transfer'   => '🔁 Transferências',
                        'match'      => '⚽ Partidas',
                        'lineup'     => '📋 Escalação',
                        'invitation' => '📨 Convites',
                    ];
                @endphp
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($types as $key => $label)
                        <button type="button" @click="filter = '{{ $key }}'"
                                :class="filter === '{{ $key }}'
                                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400'
                                    : 'border-slate-700 bg-slate-800 text-slate-400 hover:text-white'"
                                class="rounded-full border px-3 py-1 text-xs font-semibold transition">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($messages->isEmpty())
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-8 text-center text-sm text-slate-400">
                    Nenhuma mensagem ainda. Elas chegam conforme a liga avança: finanças, propostas, resultados e recados da diretoria.
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($messages as $message)
                        @php
                            $icons = ['financial' => '💰', 'transfer' => '🔁', 'match' => '⚽', 'lineup' => '📋', 'club' => '🏛', 'invitation' => '📨'];
                        @endphp
                        <div x-show="filter === 'all' || filter === '{{ $message->type }}'" x-transition
                             class="rounded-2xl border p-4 {{ $message->isUnread() ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-slate-700 bg-slate-900' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $icons[$message->type] ?? '✉️' }}</span>
                                        <h3 class="truncate font-semibold {{ $message->isUnread() ? 'text-white' : 'text-slate-300' }}">
                                            {{ $message->title }}
                                        </h3>
                                        @if ($message->isUnread())
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-400"></span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-slate-400">{{ $message->body }}</p>
                                    <p class="mt-2 text-xs text-slate-600">
                                        Rodada {{ $message->global_round }} · {{ $message->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>

                                @if ($message->isUnread() || $message->subjectUrl())
                                    <form method="POST" action="{{ route('leagues.office.messages.read', [$league, $message]) }}" class="shrink-0">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:border-slate-600 hover:text-white transition">
                                            {{ $message->subjectUrl() ? 'Abrir →' : 'Marcar lida' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
