<x-app-layout>
    {{-- Header --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 mb-1">
                <a href="{{ route('dashboard') }}" class="text-slate-500 hover:text-slate-300 transition text-sm">Dashboard</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400 text-sm">Criar Liga</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white">Criar Nova Liga</h1>
            <p class="mt-1 text-sm text-slate-400">Configure o campeonato, visibilidade e vagas disponíveis.</p>
        </div>
    </div>

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8"
         x-data="leagueCreate({{ $championships->map(fn($c) => ['id'=>$c->id,'name'=>$c->name,'type'=>$c->type,'legs'=>$c->legs,'teams_count'=>$c->teams_count,'scope'=>$c->scopeLabel()])->toJson() }})">

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-400">
                <ul class="space-y-1 list-disc list-inside">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('leagues.store') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Nome da liga --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Identificação</h2>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Nome da Liga *</label>
                <input type="text" name="name" value="{{ old('name') }}"
                    placeholder="Ex: Copa dos Amigos 2026"
                    class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    required maxlength="100" />
            </div>

            {{-- Campeonato --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Campeonato</h2>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Selecione o Campeonato *</label>
                <select name="championship_id"
                    x-model="selectedId"
                    @change="onChampionshipChange()"
                    class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    required>
                    <option value="">-- Escolha um campeonato --</option>
                    @foreach ($championships as $c)
                        <option value="{{ $c->id }}" {{ old('championship_id') === $c->id ? 'selected' : '' }}>
                            {{ $c->scopeLabel() }}
                        </option>
                    @endforeach
                </select>

                {{-- Info do campeonato selecionado --}}
                <div x-show="selected" x-transition class="mt-4 rounded-xl border border-slate-700 bg-slate-800/60 p-4 grid grid-cols-3 gap-3 text-center">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Formato</p>
                        <p class="text-sm font-semibold text-white" x-text="typeLabel(selected?.type)"></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Jogos</p>
                        <p class="text-sm font-semibold text-white" x-text="legsLabel(selected?.legs)"></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Máx. times</p>
                        <p class="text-sm font-semibold text-emerald-400" x-text="selected?.teams_count"></p>
                    </div>
                </div>
            </div>

            {{-- Visibilidade + Vagas --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Configurações</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    {{-- Visibilidade --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-3">Visibilidade</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="type" value="public" x-model="visibility" class="sr-only peer" {{ old('type','public') === 'public' ? 'checked' : '' }} />
                                <div class="rounded-xl border border-slate-600 bg-slate-800 p-3 text-center transition peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-1 h-5 w-5 text-slate-400 peer-checked:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                    <p class="text-xs font-semibold text-slate-300">Pública</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Qualquer um pode ver</p>
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="type" value="private" x-model="visibility" class="sr-only peer" {{ old('type') === 'private' ? 'checked' : '' }} />
                                <div class="rounded-xl border border-slate-600 bg-slate-800 p-3 text-center transition peer-checked:border-sky-500 peer-checked:bg-sky-500/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-1 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                    <p class="text-xs font-semibold text-slate-300">Privada</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Somente por convite</p>
                                </div>
                            </label>
                        </div>
                        <p x-show="visibility === 'private'" class="mt-2 text-xs text-sky-400">
                            Um código de convite será gerado automaticamente.
                        </p>
                    </div>

                    {{-- Número de times --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">
                            Número de Times *
                            <span x-show="selected" class="font-normal text-slate-500 ml-1">(máx. <span x-text="selected?.teams_count"></span>)</span>
                        </label>
                        <input type="number" name="max_teams"
                            :max="selected?.teams_count ?? 32"
                            :value="maxTeamsDefault()"
                            min="2"
                            value="{{ old('max_teams', 8) }}"
                            class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            required />
                        <p class="mt-1 text-xs text-slate-500">Times sem dono humano serão controlados pela IA.</p>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 mt-5">
                    {{-- Temporada inicial --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Temporada Inicial *</label>
                        <input type="number" name="season"
                            value="{{ old('season', date('Y')) }}"
                            min="1900" max="2200"
                            class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            required />
                        <p class="mt-1 text-xs text-slate-500">Ano em que a liga começa. Avança 1 a cada virada de ano.</p>
                    </div>
                    <div>{{-- placeholder para manter grid --}}</div>
                </div>

                {{-- Atribuição de times --}}
                <div class="mt-5">
                    <label class="block text-sm font-medium text-slate-300 mb-3">Atribuição de Times</label>
                    <div class="grid gap-3 sm:grid-cols-2">

                        {{-- Escolha livre --}}
                        <label class="cursor-pointer">
                            <input type="radio" name="team_assignment" value="choice"
                                   class="sr-only peer"
                                   {{ old('team_assignment', 'choice') === 'choice' ? 'checked' : '' }} />
                            <div class="rounded-xl border border-slate-600 bg-slate-800 p-4 transition
                                        peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10">
                                <div class="flex items-start gap-3">
                                    <span class="text-2xl leading-none">🏟️</span>
                                    <div>
                                        <p class="text-sm font-semibold text-white">Escolha livre</p>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            Cada manager escolhe o time que quer gerenciar entre os disponíveis.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </label>

                        {{-- Sorteio --}}
                        <label class="cursor-pointer">
                            <input type="radio" name="team_assignment" value="random"
                                   class="sr-only peer"
                                   {{ old('team_assignment') === 'random' ? 'checked' : '' }} />
                            <div class="rounded-xl border border-slate-600 bg-slate-800 p-4 transition
                                        peer-checked:border-violet-500 peer-checked:bg-violet-500/10">
                                <div class="flex items-start gap-3">
                                    <span class="text-2xl leading-none">🎲</span>
                                    <div>
                                        <p class="text-sm font-semibold text-white">Sorteio</p>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            O sistema sorteia um time aleatório para cada manager ao entrar.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </label>

                    </div>
                </div>
            </div>

            {{-- Ações --}}
            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('dashboard') }}"
                    class="rounded-xl border border-slate-600 px-5 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800">
                    Cancelar
                </a>
                <button type="submit"
                    class="rounded-xl bg-emerald-500 px-6 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                    Criar Liga
                </button>
            </div>
        </form>
    </div>

    <script>
        function leagueCreate(championships) {
            return {
                championships,
                selectedId: '{{ old('championship_id', '') }}',
                visibility: '{{ old('type', 'public') }}',
                get selected() {
                    return this.championships.find(c => c.id === this.selectedId) || null;
                },
                onChampionshipChange() {
                    // Atualiza o max_teams para o padrão do campeonato selecionado
                },
                maxTeamsDefault() {
                    if (!this.selected) return {{ old('max_teams', 8) }};
                    const max = this.selected.teams_count;
                    const old = {{ old('max_teams', 0) }};
                    return old > 0 ? Math.min(old, max) : Math.min(8, max);
                },
                typeLabel(type) {
                    const map = { league: 'Pontos corridos', cup: 'Mata-mata', mixed: 'Grupos + Mata-mata' };
                    return map[type] || type;
                },
                legsLabel(legs) {
                    const map = { single: 'Jogo único', double: 'Ida e volta' };
                    return map[legs] || legs;
                },
            };
        }
    </script>
</x-app-layout>
