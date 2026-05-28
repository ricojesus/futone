<x-app-layout>
    {{-- Header --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 mb-1">
                <a href="{{ route('leagues.index') }}" class="text-slate-500 hover:text-slate-300 transition text-sm">Minhas Ligas</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400 text-sm">Criar Liga</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white">Criar Nova Liga</h1>
            <p class="mt-1 text-sm text-slate-400">Crie um mundo de jogo e convide seus amigos. As competições serão adicionadas dentro da liga.</p>
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

            {{-- Nome --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Identificação</h2>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Nome da Liga *</label>
                <input type="text" name="name" value="{{ old('name') }}"
                    placeholder="Ex: Liga dos Amigos 2026"
                    class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    required maxlength="100" />
            </div>

            {{-- Configurações --}}
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

                    {{-- Temporada --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Temporada *</label>
                        <input type="number" name="season"
                            value="{{ old('season', date('Y')) }}"
                            min="1900" max="2200"
                            class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            required />
                        <p class="mt-1 text-xs text-slate-500">Ano da temporada desta liga.</p>
                    </div>
                </div>
            </div>

            {{-- Escolha de time --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-widest text-slate-400">Escolha de Time</h2>
                <p class="mb-4 text-xs text-slate-500">Como os jogadores recebem seus times nesta liga.</p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="team_assignment" value="manual" class="sr-only peer"
                               {{ old('team_assignment', 'manual') === 'manual' ? 'checked' : '' }} />
                        <div class="rounded-xl border border-slate-600 bg-slate-800 p-4 transition peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 h-full">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-xl">🎯</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-300 peer-checked:text-white">Escolha Livre</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Cada jogador escolhe o próprio time ao entrar na liga.</p>
                                </div>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="team_assignment" value="auto" class="sr-only peer"
                               {{ old('team_assignment') === 'auto' ? 'checked' : '' }} />
                        <div class="rounded-xl border border-slate-600 bg-slate-800 p-4 transition peer-checked:border-violet-500 peer-checked:bg-violet-500/10 h-full">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-xl">🎲</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-300">Sorteio Automático</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Jogadores entram na fila e o dono sorteia os times de uma vez.</p>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Duração --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6" x-data="{ custom: {{ old('max_seasons') && !in_array(old('max_seasons'), ['1','2','3','5']) ? 'true' : 'false' }} }">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-widest text-slate-400">Duração da Liga</h2>
                <p class="mb-4 text-xs text-slate-500">Quantas temporadas esta liga vai durar? Ao atingir o limite, a liga é encerrada automaticamente.</p>

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    @foreach ([null => 'Sem limite', 1 => '1 temp.', 2 => '2 temp.', 3 => '3 temp.', 5 => '5 temp.'] as $val => $label)
                        @php $valStr = $val === null ? '' : (string)$val; @endphp
                        <label class="cursor-pointer">
                            <input type="radio" name="max_seasons" value="{{ $valStr }}"
                                   class="sr-only peer"
                                   x-on:change="custom = false"
                                   {{ old('max_seasons', '') == $valStr && !(old('max_seasons') && !in_array(old('max_seasons'), ['','1','2','3','5'])) ? 'checked' : '' }} />
                            <div class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2.5 text-center text-xs font-semibold transition
                                        peer-checked:border-amber-500 peer-checked:bg-amber-500/10 peer-checked:text-amber-400
                                        text-slate-400 hover:border-slate-500">
                                {{ $label }}
                            </div>
                        </label>
                    @endforeach
                    {{-- Personalizado --}}
                    <label class="cursor-pointer">
                        <input type="radio" name="_max_seasons_custom_trigger" value="custom"
                               class="sr-only peer"
                               x-on:change="custom = true"
                               {{ old('max_seasons') && !in_array(old('max_seasons'), ['','1','2','3','5']) ? 'checked' : '' }} />
                        <div class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2.5 text-center text-xs font-semibold transition
                                    peer-checked:border-amber-500 peer-checked:bg-amber-500/10 peer-checked:text-amber-400
                                    text-slate-400 hover:border-slate-500">
                            Personalizado
                        </div>
                    </label>
                </div>

                {{-- Campo numérico para personalizado --}}
                <div x-show="custom" x-transition class="mt-4">
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Número de temporadas</label>
                    <input type="number"
                           x-on:input="$el.form.querySelector('[name=max_seasons]') && ($el.form.querySelector('[name=max_seasons]').value = $el.value)"
                           name="max_seasons"
                           value="{{ old('max_seasons') && !in_array(old('max_seasons'), ['','1','2','3','5']) ? old('max_seasons') : '' }}"
                           min="1" max="20" placeholder="Ex: 7"
                           class="w-40 rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-white focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500" />
                    <p class="mt-1 text-xs text-slate-500">Mínimo 1, máximo 20 temporadas.</p>
                </div>
            </div>

            {{-- Ações --}}
            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('leagues.index') }}"
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
