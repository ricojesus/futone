<x-app-layout>
    {{-- Header ──────────────────────────────────────────────────────── --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 mb-1 text-sm">
                <a href="{{ route('dashboard') }}" class="text-slate-500 hover:text-slate-300 transition">Dashboard</a>
                <span class="text-slate-700">/</span>
                <a href="{{ route('leagues.show', $league) }}" class="text-slate-500 hover:text-slate-300 transition">{{ $league->name }}</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400">Escalação</span>
            </div>
            <div class="flex items-start justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-white">Escalação</h1>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ $leagueTeam->name }} · {{ $league->name }}
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-800 px-3 py-1 text-xs font-medium text-slate-300">
                    Temporada {{ $league->season }}
                </span>
            </div>
        </div>
    </div>

    {{-- Corpo ───────────────────────────────────────────────────────── --}}
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8"
         x-data="lineupManager(
             {{ json_encode(\App\Models\LeagueLineup::FORMATIONS) }},
             {{ json_encode($currentStarters) }},
             '{{ $lineup?->formation ?? '4-4-2' }}'
         )">

        {{-- Feedback --}}
        @if (session('success'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-400">
                <ul class="space-y-1 list-disc list-inside">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('leagues.lineup.update', [$league, $leagueTeam]) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- Cabeçalho: formação + status ────────────────────────── --}}
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">

                {{-- Seletor de formação --}}
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium text-slate-400">Formação</label>
                    <select name="formation" x-model="formation" @change="onFormationChange()"
                            class="rounded-xl border border-slate-600 bg-slate-800 px-4 py-2 text-white text-sm
                                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        @foreach (array_keys(\App\Models\LeagueLineup::FORMATIONS) as $f)
                            <option value="{{ $f }}">{{ $f }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Contador de titulares --}}
                <div class="flex items-center gap-6 text-sm">
                    <span class="text-slate-500">
                        Titulares:
                        <span class="font-bold" :class="totalCount === 11 ? 'text-emerald-400' : 'text-amber-400'"
                              x-text="totalCount + '/11'"></span>
                    </span>
                    <template x-for="[role, label] in [['goalkeeper','GOL'],['defender','DEF'],['midfielder','MEI'],['forward','ATA']]" :key="role">
                        <span class="text-slate-500">
                            <span x-text="label"></span>
                            <span class="font-bold"
                                  :class="count(role) === required(role) ? 'text-emerald-400' : 'text-amber-400'"
                                  x-text="count(role) + '/' + required(role)"></span>
                        </span>
                    </template>
                </div>

                {{-- Botão salvar --}}
                <button type="submit"
                        :disabled="!isComplete()"
                        :class="isComplete()
                            ? 'bg-emerald-500 hover:bg-emerald-400 text-white cursor-pointer'
                            : 'bg-slate-700 text-slate-500 cursor-not-allowed'"
                        class="rounded-xl px-6 py-2.5 text-sm font-bold uppercase tracking-wider transition active:scale-95">
                    Salvar Escalação
                </button>
            </div>

            {{-- Grid de posições ─────────────────────────────────────── --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-4">

                @php
                    $groups = [
                        'goalkeeper' => ['label' => 'Goleiros',        'abbr' => 'GOL', 'color' => 'yellow'],
                        'defender'   => ['label' => 'Defensores',      'abbr' => 'DEF', 'color' => 'sky'],
                        'midfielder' => ['label' => 'Meios-campistas',  'abbr' => 'MEI', 'color' => 'violet'],
                        'forward'    => ['label' => 'Atacantes',        'abbr' => 'ATA', 'color' => 'emerald'],
                    ];
                    $colorMap = [
                        'yellow'  => ['border' => 'border-yellow-500/40',  'bg' => 'bg-yellow-500/10',  'text' => 'text-yellow-400',  'badge' => 'bg-yellow-500/20 text-yellow-400',  'ring' => 'ring-yellow-500'],
                        'sky'     => ['border' => 'border-sky-500/40',     'bg' => 'bg-sky-500/10',     'text' => 'text-sky-400',     'badge' => 'bg-sky-500/20 text-sky-400',     'ring' => 'ring-sky-500'],
                        'violet'  => ['border' => 'border-violet-500/40',  'bg' => 'bg-violet-500/10',  'text' => 'text-violet-400',  'badge' => 'bg-violet-500/20 text-violet-400',  'ring' => 'ring-violet-500'],
                        'emerald' => ['border' => 'border-emerald-500/40', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-400', 'badge' => 'bg-emerald-500/20 text-emerald-400', 'ring' => 'ring-emerald-500'],
                    ];
                @endphp

                @foreach ($groups as $role => $group)
                    @php $c = $colorMap[$group['color']]; @endphp

                    <div class="rounded-2xl border {{ $c['border'] }} bg-slate-900 overflow-hidden">

                        {{-- Cabeçalho do grupo --}}
                        <div class="{{ $c['bg'] }} px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="rounded-md px-2 py-0.5 text-xs font-bold {{ $c['badge'] }}">
                                    {{ $group['abbr'] }}
                                </span>
                                <span class="text-sm font-semibold text-white">{{ $group['label'] }}</span>
                            </div>
                            <span class="text-xs font-medium {{ $c['text'] }}"
                                  x-text="count('{{ $role }}') + '/' + required('{{ $role }}')"></span>
                        </div>

                        {{-- Lista de jogadores --}}
                        <div class="divide-y divide-slate-800">
                            @php
                                $groupPlayers = $players->where('position', $role);
                            @endphp

                            @forelse ($groupPlayers as $player)
                                @php $pid = $player->id; @endphp

                                {{-- Hidden input (ativo apenas quando titular) --}}
                                <template x-if="isStarter('{{ $pid }}')">
                                    <input type="hidden"
                                           name="starters[{{ $pid }}]"
                                           value="{{ $role }}">
                                </template>

                                <button type="button"
                                        @click="toggle('{{ $pid }}', '{{ $role }}')"
                                        :disabled="!isStarter('{{ $pid }}') && !canAdd('{{ $role }}')"
                                        class="w-full text-left px-4 py-3 transition
                                               disabled:opacity-40 disabled:cursor-not-allowed
                                               hover:bg-slate-800/60"
                                        :class="isStarter('{{ $pid }}')
                                            ? '{{ $c['bg'] }} ring-1 inset-ring {{ $c['ring'] }}'
                                            : 'bg-transparent'">

                                    <div class="flex items-center gap-3">
                                        {{-- Indicador titular --}}
                                        <div class="w-4 h-4 rounded-full border-2 flex-shrink-0 transition"
                                             :class="isStarter('{{ $pid }}')
                                                 ? 'border-transparent {{ $c['bg'] }} ring-2 ring-offset-1 ring-offset-slate-900 {{ $c['ring'] }}'
                                                 : 'border-slate-600'">
                                            <template x-if="isStarter('{{ $pid }}')">
                                                <div class="w-full h-full rounded-full {{ $c['bg'] }} scale-75"></div>
                                            </template>
                                        </div>

                                        {{-- Info do jogador --}}
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-white truncate">
                                                {{ $player->name ?? 'Jogador #' . substr($player->id, 0, 6) }}
                                            </p>
                                            <p class="text-xs text-slate-500 mt-0.5">
                                                Força {{ $player->strength }}
                                                · Fitness {{ $player->fitness }}%
                                                @if ($player->fitness < 60)
                                                    <span class="text-amber-400">⚠</span>
                                                @endif
                                            </p>
                                        </div>

                                        {{-- Poder efetivo --}}
                                        <div class="text-right flex-shrink-0">
                                            @php
                                                $power = round($player->strength * ($player->fitness / 100) * (float)($player->form_factor ?? 1.0));
                                            @endphp
                                            <span class="text-sm font-bold {{ $c['text'] }}">
                                                {{ $power }}
                                            </span>
                                            <p class="text-xs text-slate-600">poder</p>
                                        </div>
                                    </div>

                                    {{-- Badge: lesionado --}}
                                    @if ($player->status === 'injured')
                                        <div class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs text-red-400">
                                            🤕 Lesionado
                                        </div>
                                    @endif
                                </button>

                            @empty
                                <div class="px-4 py-6 text-center text-xs text-slate-600">
                                    Nenhum jogador nesta posição
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach

            </div>{{-- /grid --}}

            {{-- Legenda de impacto da formação ─────────────────────── --}}
            <div class="mt-8 rounded-2xl border border-slate-700 bg-slate-900 p-5">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-4">
                    Impacto da formação <span class="text-white" x-text="formation"></span> nos setores do campo
                </h3>
                <div class="grid grid-cols-5 gap-2 text-center text-xs">
                    <template x-for="(label, i) in ['⬛ Defesa','Defesa Meia','Meio-campo','Meia Ataque','Ataque ⬛']" :key="i">
                        <div class="rounded-xl border border-slate-700 p-2">
                            <p class="text-slate-500 mb-1" x-text="label"></p>
                            <p class="font-bold text-sm"
                               :class="sectorMod(i + 1) > 1.05 ? 'text-emerald-400' : sectorMod(i + 1) < 0.95 ? 'text-red-400' : 'text-slate-300'"
                               x-text="(sectorMod(i + 1) * 100).toFixed(0) + '%'"></p>
                        </div>
                    </template>
                </div>
                <p class="mt-3 text-xs text-slate-600">
                    Percentual do modificador de força do time em cada setor do campo em relação à baseline 4-4-2.
                    Acima de 100% = vantagem; abaixo = desvantagem.
                </p>
            </div>

        </form>
    </div>

    <script>
    function lineupManager(formations, currentStarters, initialFormation) {
        return {
            formations,
            formation: initialFormation,
            starters: currentStarters,  // {playerId: role}

            // ── Contadores ───────────────────────────────────────────
            get totalCount() {
                return Object.keys(this.starters).length;
            },

            count(role) {
                return Object.values(this.starters).filter(r => r === role).length;
            },

            required(role) {
                if (role === 'goalkeeper') return 1;
                return (this.formations[this.formation] || {})[role] || 0;
            },

            isComplete() {
                return this.totalCount === 11
                    && this.count('goalkeeper') === 1
                    && this.count('defender')   === this.required('defender')
                    && this.count('midfielder') === this.required('midfielder')
                    && this.count('forward')    === this.required('forward');
            },

            // ── Estado individual ────────────────────────────────────
            isStarter(playerId) {
                return Object.prototype.hasOwnProperty.call(this.starters, playerId);
            },

            canAdd(role) {
                return this.count(role) < this.required(role);
            },

            toggle(playerId, role) {
                if (this.isStarter(playerId)) {
                    const copy = { ...this.starters };
                    delete copy[playerId];
                    this.starters = copy;
                } else if (this.canAdd(role)) {
                    this.starters = { ...this.starters, [playerId]: role };
                }
            },

            // ── Mudança de formação ──────────────────────────────────
            onFormationChange() {
                // Remove titulares que excedem os slots da nova formação
                const counts = { goalkeeper: 0, defender: 0, midfielder: 0, forward: 0 };
                const newStarters = {};

                for (const [id, role] of Object.entries(this.starters)) {
                    const max = role === 'goalkeeper' ? 1 : (this.formations[this.formation]?.[role] || 0);
                    if (counts[role] < max) {
                        newStarters[id] = role;
                        counts[role]++;
                    }
                }
                this.starters = newStarters;
            },

            // ── Modificador de formação por setor (mirrors PHP) ──────
            sectorMod(sector) {
                const parts = this.formation.split('-').map(Number);
                const def = parts[0];
                const fwd = parts[parts.length - 1];
                const mid = parts.reduce((a, b) => a + b, 0) - def - fwd;

                const defScale = def / 4.0;
                const midScale = mid / 4.0;
                const fwdScale = fwd / 2.0;

                const base = 0.70;
                const flex = 0.30;

                switch (sector) {
                    case 1: return base + flex * defScale;
                    case 2: return base + flex * (defScale * 0.6 + midScale * 0.4);
                    case 3: return base + flex * midScale;
                    case 4: return base + flex * (fwdScale * 0.6 + midScale * 0.4);
                    case 5: return base + flex * fwdScale;
                    default: return 1.0;
                }
            },
        };
    }
    </script>
</x-app-layout>
