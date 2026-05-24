<x-app-layout>

    @php
        $playersJson = $players->map(fn($p) => [
            'id'          => (string) $p->id,
            'name'        => $p->name ?? ('Jogador #' . substr($p->id, 0, 6)),
            'position'    => $p->position,
            'strength'    => (int) $p->strength,
            'fitness'     => (int) ($p->fitness ?? 100),
            'form_factor' => (float) ($p->form_factor ?? 1.0),
            'status'      => $p->status ?? 'active',
        ])->values()->toJson();

        $currentStartersJson = $currentStarters instanceof \Illuminate\Support\Collection
            ? $currentStarters->toJson()
            : json_encode((object) $currentStarters);
    @endphp

    {{-- ══ Cabeçalho ═══════════════════════════════════════════════════════════ --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2 mb-1 text-xs text-slate-500">
                <a href="{{ route('dashboard') }}" class="hover:text-slate-300 transition">Dashboard</a>
                <span>/</span>
                <a href="{{ route('leagues.show', $league) }}" class="hover:text-slate-300 transition">{{ $league->name }}</a>
                <span>/</span>
                <span class="text-slate-400">Escalação</span>
            </div>
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-extrabold text-white">Escalação</h1>
                    <p class="mt-0.5 text-sm text-slate-400">
                        {{ $leagueTeam->name }}
                        <span class="text-slate-600 mx-1">·</span>
                        {{ $league->name }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-800 px-3 py-1 text-xs font-medium text-slate-400">
                        Temporada {{ $league->season }}
                    </span>
                    @if($lineup?->updated_at)
                        <span class="text-xs text-slate-600">
                            Salva {{ $lineup->updated_at->diffForHumans() }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Corpo ════════════════════════════════════════════════════════════════ --}}
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8"
         x-data="lineupManager(
             {{ json_encode(\App\Models\CompetitionLineup::FORMATIONS) }},
             {{ $currentStartersJson }},
             '{{ $lineup?->formation ?? '4-4-2' }}',
             {{ $playersJson }}
         )">

        {{-- Alertas --}}
        @if (session('success'))
            <div class="mb-5 flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-400">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-400">
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

            {{-- ── Barra de ação ──────────────────────────────────────────────── --}}
            <div class="mb-5 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 sm:px-5">

                {{-- Linha superior: Formação + Salvar --}}
                <div class="flex items-center justify-between gap-3 flex-wrap">

                    {{-- Formação --}}
                    <div class="flex items-center gap-2 sm:gap-3">
                        <label class="text-xs font-semibold uppercase tracking-widest text-slate-500 hidden sm:block">Formação</label>
                        <select name="formation" x-model="formation" @change="onFormationChange()"
                                class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-1.5 sm:px-4 sm:py-2 text-white text-sm
                                       focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                            @foreach (array_keys(\App\Models\CompetitionLineup::FORMATIONS) as $f)
                                <option value="{{ $f }}" {{ ($lineup?->formation ?? '4-4-2') === $f ? 'selected' : '' }}>
                                    {{ $f }}
                                </option>
                            @endforeach
                        </select>
                        {{-- Total de titulares (visível só em mobile) --}}
                        <span class="sm:hidden font-bold tabular-nums text-xs"
                              :class="totalCount === 11 ? 'text-emerald-400' : 'text-amber-400'"
                              x-text="totalCount + '/11'"></span>
                    </div>

                    {{-- Salvar --}}
                    <button type="submit"
                            :disabled="!isComplete()"
                            :class="isComplete()
                                ? 'bg-emerald-500 hover:bg-emerald-400 cursor-pointer shadow-lg shadow-emerald-500/20'
                                : 'bg-slate-700 text-slate-500 cursor-not-allowed'"
                            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 sm:px-5 sm:py-2.5 text-sm font-bold text-white transition active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span class="hidden xs:inline">Salvar</span>
                        <span class="xs:hidden">Salvar</span>
                    </button>
                </div>

                {{-- Linha inferior: Contadores por posição (todas as telas) --}}
                <div class="mt-2.5 flex items-center gap-3 sm:gap-4 text-xs flex-wrap">
                    <template x-for="[role, abbr, color] in [
                        ['goalkeeper','GOL','#f59e0b'],
                        ['defender','DEF','#0ea5e9'],
                        ['midfielder','MEI','#8b5cf6'],
                        ['forward','ATA','#10b981']
                    ]" :key="role">
                        <div class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" :style="`background:${color}`"></span>
                            <span class="font-semibold text-slate-500" x-text="abbr"></span>
                            <span class="font-bold tabular-nums"
                                  :class="count(role) === required(role) ? 'text-emerald-400' : 'text-amber-400'"
                                  x-text="count(role) + '/' + required(role)"></span>
                        </div>
                    </template>
                    {{-- Total (visível em telas maiores) --}}
                    <div class="hidden sm:flex items-center gap-1 ml-1 pl-3 border-l border-slate-700">
                        <span class="font-bold tabular-nums"
                              :class="totalCount === 11 ? 'text-emerald-400' : 'text-amber-400'"
                              x-text="totalCount + '/11 titulares'"></span>
                    </div>
                </div>
            </div>

            {{-- ── Grade principal: campo + banco ────────────────────────────── --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3 lg:items-start">

                {{-- ════ CAMPO DE FUTEBOL ════════════════════════════════════════ --}}
                <div class="lg:col-span-2 space-y-4">

                    {{-- Campo --}}
                    {{-- h-64 = 256px (mobile) → sm:h-80 = 320px → lg:h-[520px] (desktop) --}}
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl select-none h-64 sm:h-80 lg:h-[520px]"
                         style="background: repeating-linear-gradient(
                                    90deg,
                                    #14532d 0px, #14532d 50px,
                                    #166534 50px, #166534 100px
                                );">

                        {{-- ── Linhas do campo ──────────────────────────────── --}}

                        {{-- Borda interna --}}
                        <div class="absolute inset-3 border-2 border-white/25 rounded-sm pointer-events-none"></div>

                        {{-- Linha do meio (vertical) --}}
                        <div class="absolute top-3 bottom-3 left-1/2 w-px bg-white/25 pointer-events-none"></div>

                        {{-- Círculo central --}}
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/25 pointer-events-none"
                             style="width: 110px; height: 110px;"></div>

                        {{-- Ponto central --}}
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full bg-white/40 pointer-events-none"></div>

                        {{-- Área de penalti esquerda --}}
                        <div class="absolute top-1/2 -translate-y-1/2 left-3 border-2 border-white/25 border-l-0 pointer-events-none"
                             style="width: 17%; height: 66%;"></div>

                        {{-- Área pequena esquerda --}}
                        <div class="absolute top-1/2 -translate-y-1/2 left-3 border-2 border-white/25 border-l-0 pointer-events-none"
                             style="width: 7%; height: 38%;"></div>

                        {{-- Área de penalti direita --}}
                        <div class="absolute top-1/2 -translate-y-1/2 right-3 border-2 border-white/25 border-r-0 pointer-events-none"
                             style="width: 17%; height: 66%;"></div>

                        {{-- Área pequena direita --}}
                        <div class="absolute top-1/2 -translate-y-1/2 right-3 border-2 border-white/25 border-r-0 pointer-events-none"
                             style="width: 7%; height: 38%;"></div>

                        {{-- Gol esquerdo (linha grossa) --}}
                        <div class="absolute top-1/2 -translate-y-1/2 left-0 bg-white/30 pointer-events-none"
                             style="width: 4px; height: 22%;"></div>

                        {{-- Gol direito --}}
                        <div class="absolute top-1/2 -translate-y-1/2 right-0 bg-white/30 pointer-events-none"
                             style="width: 4px; height: 22%;"></div>

                        {{-- ── Rótulo de formação ───────────────────────────── --}}
                        <div class="absolute top-2 left-0 right-0 flex justify-center pointer-events-none">
                            <span class="bg-black/30 rounded-full px-3 py-0.5 text-white/70 text-xs font-bold tracking-widest"
                                  x-text="formation"></span>
                        </div>

                        {{-- ── Setas de sentido de ataque ───────────────────── --}}
                        <div class="absolute bottom-2 left-0 right-0 flex justify-center pointer-events-none">
                            <span class="text-white/20 text-xs tracking-widest">⬅ defesa &nbsp;&nbsp;&nbsp; ataque ➡</span>
                        </div>

                        {{-- ── Zonas táticas dinâmicas (se movem com a formação) ── --}}

                        {{-- Fundo semitransparente da zona de defesa --}}
                        <div class="absolute top-0 bottom-0 pointer-events-none transition-all duration-500"
                             :style="`left:0; width:${formationZones().defMidX}%; background:rgba(14,165,233,0.04);`"></div>

                        {{-- Fundo semitransparente da zona de ataque --}}
                        <div class="absolute top-0 bottom-0 pointer-events-none transition-all duration-500"
                             :style="`left:${formationZones().midFwdX}%; right:0; background:rgba(16,185,129,0.04);`"></div>

                        {{-- Linha divisória DEF | MEI (dashed vertical) --}}
                        <div class="absolute top-4 bottom-4 w-px pointer-events-none transition-all duration-500"
                             style="background: repeating-linear-gradient(180deg, rgba(255,255,255,0.25) 0, rgba(255,255,255,0.25) 6px, transparent 6px, transparent 12px);"
                             :style="`left:${formationZones().defMidX}%`"></div>

                        {{-- Linha divisória MEI | ATA --}}
                        <div class="absolute top-4 bottom-4 w-px pointer-events-none transition-all duration-500"
                             style="background: repeating-linear-gradient(180deg, rgba(255,255,255,0.25) 0, rgba(255,255,255,0.25) 6px, transparent 6px, transparent 12px);"
                             :style="`left:${formationZones().midFwdX}%`"></div>

                        {{-- Labels das zonas (topo) --}}
                        <div class="absolute top-3 pointer-events-none text-center transition-all duration-500"
                             :style="`left: 10%; width: ${formationZones().defMidX - 10}%`">
                            <span class="text-sky-300/40 font-bold tracking-widest" style="font-size:9px;">DEF</span>
                        </div>
                        <div class="absolute top-3 pointer-events-none text-center transition-all duration-500"
                             :style="`left: ${formationZones().defMidX}%; width: ${formationZones().midFwdX - formationZones().defMidX}%`">
                            <span class="text-white/30 font-bold tracking-widest" style="font-size:9px;">MEI</span>
                        </div>
                        <div class="absolute top-3 pointer-events-none text-center transition-all duration-500"
                             :style="`left: ${formationZones().midFwdX}%; width: ${90 - formationZones().midFwdX}%`">
                            <span class="text-emerald-300/40 font-bold tracking-widest" style="font-size:9px;">ATA</span>
                        </div>

                        {{-- ── Tokens dos jogadores (Alpine) ───────────────── --}}
                        <template x-for="p in pitchPlayers()" :key="p.id">
                            <div :style="`left: ${p.x}%; top: ${p.y}%;`"
                                 class="absolute -translate-x-1/2 -translate-y-1/2 cursor-pointer group z-10"
                                 @click="toggle(p.id, p.role)"
                                 :title="`Clique para remover ${shortName(p.id)}`">

                                {{-- Círculo principal
                                     Mobile: w-8 h-8 (32px)  sm: w-10 h-10 (40px)  lg: w-12 h-12 (48px) --}}
                                <div class="relative w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12
                                            rounded-full flex flex-col items-center justify-center border-2 shadow-xl
                                            transition-all duration-150 group-hover:scale-110 group-hover:shadow-2xl"
                                     :style="tokenStyle(p.role)">

                                    {{-- Nome abreviado --}}
                                    <span class="text-white font-bold text-center leading-none px-0.5 z-10 relative"
                                          style="font-size: 7px; max-width: 30px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;"
                                          x-text="shortName(p.id)"></span>

                                    {{-- Poder do jogador (oculto em mobile) --}}
                                    <span class="hidden sm:block text-white/70 font-semibold z-10 relative mt-0.5"
                                          style="font-size: 7px;"
                                          x-text="playerPower(p.id)"></span>

                                    {{-- Overlay de remoção ao hover --}}
                                    <div class="absolute inset-0 rounded-full bg-black/60 flex items-center justify-center
                                                opacity-0 group-hover:opacity-100 transition-opacity z-20">
                                        <svg class="w-3 h-3 sm:w-4 sm:h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </div>
                                </div>

                                {{-- Rótulo do role abaixo do token (oculto em mobile) --}}
                                <div class="absolute -bottom-4 left-1/2 -translate-x-1/2 whitespace-nowrap hidden sm:block">
                                    <span class="rounded px-1 text-white/60 font-semibold" style="font-size: 7px;"
                                          x-text="roleAbbr(p.role)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- Estado vazio --}}
                        <template x-if="totalCount === 0">
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white/15 mb-2 sm:mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                                </svg>
                                {{-- Mobile: "abaixo"; desktop: "ao lado" --}}
                                <p class="text-white/25 text-xs sm:text-sm text-center px-8 sm:px-12 lg:hidden">
                                    Selecione os jogadores no painel abaixo
                                </p>
                                <p class="text-white/25 text-sm text-center px-12 hidden lg:block">
                                    Selecione os jogadores no painel ao lado
                                </p>
                            </div>
                        </template>

                    </div>{{-- /campo --}}

                    {{-- ── Impacto da formação nos setores ────────────────── --}}
                    <div class="rounded-2xl border border-slate-700 bg-slate-900 p-3 sm:p-4">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-2 sm:mb-3">
                            <span class="hidden sm:inline">Modificador de força por setor — </span>
                            <span class="sm:hidden">Força por setor · </span>
                            <span class="text-white" x-text="formation"></span>
                        </h3>
                        <div class="grid grid-cols-5 gap-1.5 sm:gap-2 text-center">
                            <template x-for="(info, i) in [
                                {label:'DEF',     labelFull:'Defesa'},
                                {label:'D-M',     labelFull:'Def-Mei'},
                                {label:'MEI',     labelFull:'Meio'},
                                {label:'M-A',     labelFull:'Mei-Ata'},
                                {label:'ATA',     labelFull:'Ataque'}
                            ]" :key="i">
                                <div class="rounded-xl border p-1.5 sm:p-2 transition"
                                     :class="sectorMod(i+1) > 1.05 ? 'border-emerald-500/40 bg-emerald-500/10'
                                           : sectorMod(i+1) < 0.95 ? 'border-red-500/30 bg-red-500/10'
                                           : 'border-slate-700 bg-slate-800/50'">
                                    {{-- Label curto no mobile, completo no desktop --}}
                                    <p class="text-slate-500 mb-0.5 sm:mb-1 sm:hidden" style="font-size:9px;" x-text="info.label"></p>
                                    <p class="text-slate-500 text-xs mb-1 hidden sm:block" x-text="info.labelFull"></p>
                                    <p class="font-bold text-xs sm:text-sm"
                                       :class="sectorMod(i+1) > 1.05 ? 'text-emerald-400'
                                             : sectorMod(i+1) < 0.95 ? 'text-red-400'
                                             : 'text-slate-300'"
                                       x-text="(sectorMod(i+1) * 100).toFixed(0) + '%'"></p>
                                </div>
                            </template>
                        </div>
                        <p class="mt-2 text-xs text-slate-600 hidden sm:block">
                            Baseline: 4-4-2 = 100% em todos os setores. Acima = vantagem, abaixo = desvantagem.
                        </p>
                    </div>

                </div>{{-- /pitch col --}}

                {{-- ════ BANCO / SELEÇÃO ══════════════════════════════════════ --}}
                <div class="lg:col-span-1 lg:sticky lg:top-4">
                    {{-- Título do banco (mobile) --}}
                    <h2 class="lg:hidden text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3 px-1">
                        Jogadores disponíveis
                    </h2>
                    <div class="space-y-3 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:pr-1">

                        @php
                            $roleConfig = [
                                'goalkeeper' => [
                                    'label' => 'Goleiros',
                                    'abbr'  => 'GOL',
                                    'hex'   => '#f59e0b',
                                    'bghex' => 'rgba(245,158,11,0.15)',
                                ],
                                'defender' => [
                                    'label' => 'Defensores',
                                    'abbr'  => 'DEF',
                                    'hex'   => '#0ea5e9',
                                    'bghex' => 'rgba(14,165,233,0.15)',
                                ],
                                'midfielder' => [
                                    'label' => 'Meios-campistas',
                                    'abbr'  => 'MEI',
                                    'hex'   => '#8b5cf6',
                                    'bghex' => 'rgba(139,92,246,0.15)',
                                ],
                                'forward' => [
                                    'label' => 'Atacantes',
                                    'abbr'  => 'ATA',
                                    'hex'   => '#10b981',
                                    'bghex' => 'rgba(16,185,129,0.15)',
                                ],
                            ];
                        @endphp

                        @foreach ($roleConfig as $role => $cfg)
                            <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">

                                {{-- Cabeçalho do grupo --}}
                                <div class="flex items-center justify-between px-4 py-2.5"
                                     style="background: {{ $cfg['bghex'] }}; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold px-2 py-0.5 rounded-md"
                                              style="background: {{ $cfg['bghex'] }}; color: {{ $cfg['hex'] }};">
                                            {{ $cfg['abbr'] }}
                                        </span>
                                        <span class="text-sm font-semibold text-white">{{ $cfg['label'] }}</span>
                                    </div>
                                    <span class="text-xs font-bold tabular-nums"
                                          :class="count('{{ $role }}') === required('{{ $role }}') ? 'text-emerald-400' : 'text-amber-400'"
                                          x-text="count('{{ $role }}') + '/' + required('{{ $role }}')">
                                    </span>
                                </div>

                                {{-- Lista de jogadores --}}
                                <div class="divide-y divide-slate-800/60">
                                    @php $groupPlayers = $players->where('position', $role); @endphp

                                    @forelse ($groupPlayers as $player)
                                        @php $pid = (string) $player->id; @endphp

                                        {{-- Input oculto (só ativo quando titular) --}}
                                        <template x-if="isStarter('{{ $pid }}')">
                                            <input type="hidden"
                                                   name="starters[{{ $pid }}]"
                                                   value="{{ $role }}">
                                        </template>

                                        @php
                                            $fitness   = (int) ($player->fitness   ?? 100);
                                            $injured   = $player->status === 'injured';
                                            $power     = round($player->strength * ($fitness / 100) * (float) ($player->form_factor ?? 1.0));

                                            // Cores da barra de fitness
                                            $fitColor  = match(true) {
                                                $fitness >= 80 => '#10b981', // verde
                                                $fitness >= 60 => '#f59e0b', // âmbar
                                                $fitness >= 40 => '#f97316', // laranja
                                                default        => '#ef4444', // vermelho
                                            };
                                            $fitLabel  = match(true) {
                                                $fitness >= 80 => 'text-emerald-400',
                                                $fitness >= 60 => 'text-amber-400',
                                                $fitness >= 40 => 'text-orange-400',
                                                default        => 'text-red-400',
                                            };
                                        @endphp

                                        {{-- Card do jogador --}}
                                        <button type="button"
                                                @click="toggle('{{ $pid }}', '{{ $role }}')"
                                                :disabled="!isStarter('{{ $pid }}') && (!canAdd('{{ $role }}') || {{ $injured ? 'true' : 'false' }})"
                                                class="group/card w-full text-left px-3 py-2.5 transition-colors
                                                       disabled:opacity-40 disabled:cursor-not-allowed"
                                                :class="isStarter('{{ $pid }}') ? 'bg-slate-800/80' : 'hover:bg-slate-800/50'">

                                            <div class="flex items-center gap-2">

                                                {{-- Indicador visual (dot) --}}
                                                <div class="w-2 h-2 rounded-full flex-shrink-0 border transition-all"
                                                     :style="isStarter('{{ $pid }}')
                                                         ? 'background:{{ $cfg['hex'] }}; border-color:{{ $cfg['hex'] }};'
                                                         : 'background:transparent; border-color:#374151;'">
                                                </div>

                                                {{-- Info do jogador --}}
                                                <div class="flex-1 min-w-0">

                                                    {{-- Linha 1: nome + badge lesão --}}
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <p class="text-xs font-semibold truncate leading-tight"
                                                           :class="isStarter('{{ $pid }}') ? 'text-white' : 'text-slate-300'">
                                                            {{ $player->name }}
                                                        </p>
                                                        @if ($injured)
                                                            <span class="flex-shrink-0 inline-flex items-center gap-0.5 rounded px-1 py-px
                                                                         text-red-400 bg-red-500/15 border border-red-500/25 leading-none font-semibold uppercase tracking-wide"
                                                                  style="font-size: 8px;">
                                                                🩹 Lesionado
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- Linha 2: força base + barra de fitness --}}
                                                    <div class="flex items-center gap-2 mt-0.5">
                                                        <span class="text-slate-600 tabular-nums" style="font-size:10px;">
                                                            {{ $player->strength }}
                                                        </span>

                                                        {{-- Barra de fitness --}}
                                                        <div class="flex items-center gap-1">
                                                            <div class="w-14 h-1 rounded-full bg-slate-700/80 overflow-hidden">
                                                                <div class="h-full rounded-full"
                                                                     style="width: {{ $fitness }}%; background: {{ $fitColor }};"></div>
                                                            </div>
                                                            <span class="tabular-nums font-medium {{ $fitLabel }}" style="font-size:9px;">
                                                                {{ $fitness }}%
                                                            </span>
                                                        </div>
                                                    </div>

                                                </div>

                                                {{-- Poder efetivo --}}
                                                <span class="flex-shrink-0 text-xs font-bold tabular-nums transition"
                                                      :style="isStarter('{{ $pid }}') ? 'color:{{ $cfg['hex'] }}' : 'color:#4b5563'">
                                                    {{ $power }}
                                                </span>

                                                {{-- Ícone de ação --}}
                                                <div class="flex-shrink-0 w-4 text-center">
                                                    @if ($injured)
                                                        {{-- Jogador lesionado: nunca pode ser adicionado --}}
                                                        <svg class="w-3.5 h-3.5 text-red-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                        </svg>
                                                    @else
                                                        <template x-if="isStarter('{{ $pid }}')">
                                                            <svg class="w-3.5 h-3.5 text-slate-500 group-hover/card:text-red-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </template>
                                                        <template x-if="!isStarter('{{ $pid }}')">
                                                            <svg class="w-3.5 h-3.5 text-slate-700 group-hover/card:text-slate-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                            </svg>
                                                        </template>
                                                    @endif
                                                </div>

                                            </div>
                                        </button>

                                    @empty
                                        <div class="px-4 py-6 text-center text-xs text-slate-600">
                                            Nenhum jogador nesta posição
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach

                    </div>{{-- /scroll wrapper --}}
                </div>{{-- /bench col --}}

            </div>{{-- /grid --}}
        </form>
    </div>

    {{-- ══ JavaScript ══════════════════════════════════════════════════════════ --}}
    <script>
    function lineupManager(formations, currentStarters, initialFormation, allPlayers) {
        return {
            formations,
            formation:  initialFormation,
            starters:   Object.assign({}, currentStarters),  // {leaguePlayerId: role}
            allPlayers,                                        // array de objetos

            // ── Lookup de jogador ──────────────────────────────────────────────
            playerById(id) {
                return this.allPlayers.find(p => p.id === id) || null;
            },

            shortName(id) {
                const p = this.playerById(id);
                if (!p) return '?';
                const parts = p.name.trim().split(' ');
                const last  = parts[parts.length - 1];
                if (last.length > 9) return last.substring(0, 9) + '.';
                return last;
            },

            playerPower(id) {
                const p = this.playerById(id);
                if (!p) return '';
                return Math.round(p.strength * (p.fitness / 100) * p.form_factor);
            },

            // ── Decomposição da formação ───────────────────────────────────────
            //   Retorna { def, mid, fwd } a partir da string "4-4-2", "4-2-3-1" etc.
            formationParts() {
                const parts = this.formation.split('-').map(Number);
                const def   = parts[0];
                const fwd   = parts[parts.length - 1];
                const mid   = parts.reduce((a, b) => a + b, 0) - def - fwd;
                return { def, mid, fwd };
            },

            // ── Posições x das linhas (% do campo) ────────────────────────────
            //   Campo útil: x = 18% … 87%  (range = 69%)
            //   GK: 9% (fixo).
            //   Cada linha fica no centro da sua "fatia" proporcional ao nº de jogadores.
            //
            //   Fórmula:
            //     defX = S + R × (def/2) / 10
            //     midX = S + R × (def + mid/2) / 10
            //     fwdX = S + R × (def + mid + fwd/2) / 10
            //
            //   Exemplos (S=18, R=69):
            //     4-4-2  → DEF 31.8%  MEI 59.4%  ATA 80.1%
            //     3-5-2  → DEF 28.4%  MEI 56.0%  ATA 80.1%
            //     4-3-3  → DEF 31.8%  MEI 56.0%  ATA 76.7%
            //     5-3-2  → DEF 35.3%  MEI 62.9%  ATA 80.1%
            //     5-4-1  → DEF 35.3%  MEI 66.3%  ATA 83.6%
            formationXPositions() {
                const { def, mid, fwd } = this.formationParts();
                const S = 18, R = 69;
                return {
                    goalkeeper: 9,
                    defender:   S + R * (def / 2)             / 10,
                    midfielder: S + R * (def + mid / 2)       / 10,
                    forward:    S + R * (def + mid + fwd / 2) / 10,
                };
            },

            // ── Fronteiras das zonas táticas no campo ──────────────────────────
            //   defMidX = limite entre zona DEF e MEI
            //   midFwdX = limite entre zona MEI e ATA
            formationZones() {
                const { def, mid } = this.formationParts();
                const S = 18, R = 69;
                return {
                    defMidX: S + R * (def / 10),
                    midFwdX: S + R * ((def + mid) / 10),
                };
            },

            // ── Layout do campo (landscape: GK esq → FWD dir) ─────────────────
            pitchPlayers() {
                const groups = {
                    goalkeeper: [], defender: [], midfielder: [], forward: [],
                };
                for (const [id, role] of Object.entries(this.starters)) {
                    if (groups[role] !== undefined) groups[role].push(id);
                }

                const xPos = this.formationXPositions();
                const result = [];

                for (const [role, ids] of Object.entries(groups)) {
                    const x = xPos[role] ?? 50;
                    ids.forEach((id, i) => {
                        // y distribuído uniformemente na vertical
                        result.push({
                            id,
                            role,
                            x,
                            y: (i + 1) / (ids.length + 1) * 100,
                        });
                    });
                }
                return result;
            },

            // ── Estilo dos tokens ──────────────────────────────────────────────
            tokenStyle(role) {
                const palette = {
                    goalkeeper: { bg: '#b45309', border: 'rgba(251,191,36,0.8)'  },
                    defender:   { bg: '#0369a1', border: 'rgba(125,211,252,0.8)' },
                    midfielder: { bg: '#6d28d9', border: 'rgba(196,181,253,0.8)' },
                    forward:    { bg: '#047857', border: 'rgba(110,231,183,0.8)' },
                };
                const c = palette[role] || { bg: '#374151', border: 'rgba(255,255,255,0.3)' };
                return `background-color:${c.bg}; border-color:${c.border};`;
            },

            roleAbbr(role) {
                return { goalkeeper:'GOL', defender:'DEF', midfielder:'MEI', forward:'ATA' }[role] || role;
            },

            // ── Contadores ─────────────────────────────────────────────────────
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

            // ── Estado individual ──────────────────────────────────────────────
            isStarter(playerId) {
                return Object.prototype.hasOwnProperty.call(this.starters, playerId);
            },

            canAdd(role) {
                return this.count(role) < this.required(role);
            },

            toggle(playerId, role) {
                if (this.isStarter(playerId)) {
                    const next = { ...this.starters };
                    delete next[playerId];
                    this.starters = next;
                } else if (this.canAdd(role)) {
                    this.starters = { ...this.starters, [playerId]: role };
                }
            },

            // ── Troca de formação ──────────────────────────────────────────────
            onFormationChange() {
                const counts   = { goalkeeper: 0, defender: 0, midfielder: 0, forward: 0 };
                const newStarters = {};

                for (const [id, role] of Object.entries(this.starters)) {
                    const max = role === 'goalkeeper'
                        ? 1
                        : (this.formations[this.formation]?.[role] || 0);

                    if (counts[role] < max) {
                        newStarters[id] = role;
                        counts[role]++;
                    }
                }
                this.starters = newStarters;
            },

            // ── Modificador de formação por setor (espelha PHP) ────────────────
            sectorMod(sector) {
                const { def, mid, fwd } = this.formationParts();

                const defScale = def / 4.0;
                const midScale = mid / 4.0;
                const fwdScale = fwd / 2.0;
                const base = 0.70, flex = 0.30;

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
