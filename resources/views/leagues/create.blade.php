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
            <p class="mt-1 text-sm text-slate-400">Configure o formato, visibilidade e convide seus amigos.</p>
        </div>
    </div>

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">

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

            {{-- Formato do campeonato --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Formato</h2>

                {{-- Tipo --}}
                <label class="block text-sm font-medium text-slate-300 mb-3">Estilo de competição</label>
                <div class="grid gap-3 sm:grid-cols-3 mb-6">

                    <label class="cursor-pointer">
                        <input type="radio" name="format" value="league" class="sr-only peer"
                               {{ old('format', 'league') === 'league' ? 'checked' : '' }} />
                        <div class="rounded-xl border border-slate-600 bg-slate-800 p-4 transition
                                    peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 h-full">
                            <div class="text-2xl mb-2">🏆</div>
                            <p class="text-sm font-semibold text-white">Pontos corridos</p>
                            <p class="text-xs text-slate-400 mt-1">Todos jogam contra todos. Mais pontos vence.</p>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="format" value="cup" class="sr-only peer"
                               {{ old('format') === 'cup' ? 'checked' : '' }} />
                        <div class="rounded-xl border border-slate-600 bg-slate-800 p-4 transition
                                    peer-checked:border-amber-500 peer-checked:bg-amber-500/10 h-full">
                            <div class="text-2xl mb-2">⚡</div>
                            <p class="text-sm font-semibold text-white">Mata-mata</p>
                            <p class="text-xs text-slate-400 mt-1">Eliminação direta a cada fase.</p>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="format" value="mixed" class="sr-only peer"
                               {{ old('format') === 'mixed' ? 'checked' : '' }} />
                        <div class="rounded-xl border border-slate-600 bg-slate-800 p-4 transition
                                    peer-checked:border-violet-500 peer-checked:bg-violet-500/10 h-full">
                            <div class="text-2xl mb-2">🎯</div>
                            <p class="text-sm font-semibold text-white">Grupos + Mata-mata</p>
                            <p class="text-xs text-slate-400 mt-1">Fase de grupos seguida de eliminatórias.</p>
                        </div>
                    </label>
                </div>

                {{-- Jogos --}}
                <label class="block text-sm font-medium text-slate-300 mb-3">Jogos por confronto</label>
                <div class="flex gap-3">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="legs" value="double" class="sr-only peer"
                               {{ old('legs', 'double') === 'double' ? 'checked' : '' }} />
                        <div class="rounded-xl border border-slate-600 bg-slate-800 p-3 text-center transition
                                    peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10">
                            <p class="text-sm font-semibold text-white">Ida e volta</p>
                            <p class="text-xs text-slate-500 mt-0.5">2 jogos por duelo, alternando mando</p>
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="legs" value="single" class="sr-only peer"
                               {{ old('legs') === 'single' ? 'checked' : '' }} />
                        <div class="rounded-xl border border-slate-600 bg-slate-800 p-3 text-center transition
                                    peer-checked:border-amber-500 peer-checked:bg-amber-500/10">
                            <p class="text-sm font-semibold text-white">Jogo único</p>
                            <p class="text-xs text-slate-500 mt-0.5">1 jogo por duelo, campo neutro ou sorteado</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Visibilidade + Times + Temporada --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Configurações</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    {{-- Visibilidade --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-3">Visibilidade</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="access" value="public" class="sr-only peer"
                                       {{ old('access', 'public') === 'public' ? 'checked' : '' }} />
                                <div class="rounded-xl border border-slate-600 bg-slate-800 p-3 text-center transition peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-1 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                    <p class="text-xs font-semibold text-slate-300">Pública</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Qualquer um pode ver</p>
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="access" value="private" class="sr-only peer"
                                       {{ old('access') === 'private' ? 'checked' : '' }} />
                                <div class="rounded-xl border border-slate-600 bg-slate-800 p-3 text-center transition peer-checked:border-sky-500 peer-checked:bg-sky-500/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-1 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                    <p class="text-xs font-semibold text-slate-300">Privada</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Somente por convite</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Número de times --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Número de Times *</label>
                        <input type="number" name="max_teams"
                            value="{{ old('max_teams', 8) }}"
                            min="2" max="32"
                            class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            required />
                        <p class="mt-1 text-xs text-slate-500">Times sem dono humano serão controlados pela IA. Máx. 32.</p>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 mt-5">
                    {{-- Temporada --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Temporada Inicial *</label>
                        <input type="number" name="season"
                            value="{{ old('season', date('Y')) }}"
                            min="1900" max="2200"
                            class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            required />
                        <p class="mt-1 text-xs text-slate-500">Ano em que a liga começa.</p>
                    </div>
                    <div>{{-- placeholder --}}</div>
                </div>

                {{-- Atribuição de times --}}
                <div class="mt-5">
                    <label class="block text-sm font-medium text-slate-300 mb-3">Atribuição de Times</label>
                    <div class="grid gap-3 sm:grid-cols-2">

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
</x-app-layout>
