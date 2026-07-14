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
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">

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
