<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">

        <div class="mb-8">
            <a href="{{ route('admin.players') }}"
                class="mb-4 inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-300 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Voltar para Jogadores
            </a>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração</p>
            <h1 class="text-2xl font-extrabold text-white">Novo Jogador</h1>
        </div>

        <form method="POST" action="{{ route('admin.players.store') }}" enctype="multipart/form-data"
            class="rounded-2xl border border-slate-700 bg-slate-900 p-6 space-y-5">
            @csrf

            {{-- Nome --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-300">Nome <span class="text-red-400">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder-slate-500
                           focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    placeholder="Ex: Vinicius Júnior" />
                @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Posição + Força --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-300">Posição <span class="text-red-400">*</span></label>
                    <select name="position" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white
                               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">Selecione...</option>
                        @foreach($positions as $value => $label)
                            <option value="{{ $value }}" {{ old('position') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('position') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-300">Força (1–99) <span class="text-red-400">*</span></label>
                    <input type="number" name="strength" value="{{ old('strength', 70) }}" min="1" max="99" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white
                               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
                    @error('strength') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Stamina --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-300">
                    Stamina (1–100) <span class="text-red-400">*</span>
                    <span class="ml-1 text-xs font-normal text-slate-500">— resistência física do jogador durante a partida</span>
                </label>
                <input type="number" name="stamina" value="{{ old('stamina', 100) }}" min="1" max="100" required
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white
                           focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
                @error('stamina') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Nacionalidade + Idade --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-300">Nacionalidade</label>
                    <input type="text" name="nationality" value="{{ old('nationality') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder-slate-500
                               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        placeholder="Ex: Brasileira" />
                    @error('nationality') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-300">Idade</label>
                    <input type="number" name="age" value="{{ old('age') }}" min="15" max="50"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white
                               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
                    @error('age') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Foto --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-300">Foto do jogador</label>
                <input type="file" name="photo" accept="image/*"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300
                           file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-1 file:text-xs file:text-white file:cursor-pointer" />
                <p class="mt-1 text-xs text-slate-500">PNG, JPG ou WEBP. Máximo 2MB.</p>
                @error('photo') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Ações --}}
            <div class="flex items-center justify-end gap-3 border-t border-slate-800 pt-5">
                <a href="{{ route('admin.players') }}"
                    class="rounded-xl border border-slate-700 px-5 py-2.5 text-sm font-medium text-slate-400 transition hover:bg-slate-800 hover:text-white">
                    Cancelar
                </a>
                <button type="submit"
                    class="rounded-xl bg-emerald-500 px-6 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                    Salvar jogador
                </button>
            </div>
        </form>

    </div>
</x-app-layout>
