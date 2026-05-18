{{-- Name --}}
<div>
    <label for="name" class="block text-sm font-medium text-slate-300">Nome <span class="text-rose-400">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $championship->name ?? '') }}"
        class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white placeholder-slate-500
               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
               @error('name') border-rose-500 @enderror"
        placeholder="Ex: Campeonato Brasileiro Série A">
    @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
</div>

{{-- Escopo geográfico --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="state_id" class="block text-sm font-medium text-slate-300">Estado</label>
        <select id="state_id" name="state_id"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('state_id') border-rose-500 @enderror">
            <option value="">— Nacional / Internacional —</option>
            @foreach($states as $state)
                <option value="{{ $state->id }}"
                    {{ old('state_id', $championship->state_id ?? '') === $state->id ? 'selected' : '' }}>
                    {{ $state->name }} ({{ $state->code }}) — {{ $state->country?->flag }}
                </option>
            @endforeach
        </select>
        @error('state_id')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="country_id" class="block text-sm font-medium text-slate-300">País</label>
        <select id="country_id" name="country_id"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('country_id') border-rose-500 @enderror">
            <option value="">— Sem país —</option>
            @foreach($countries as $country)
                <option value="{{ $country->id }}"
                    {{ old('country_id', $championship->country_id ?? '') === $country->id ? 'selected' : '' }}>
                    {{ $country->flag }} {{ $country->name }}
                </option>
            @endforeach
        </select>
        @error('country_id')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>
</div>

{{-- Formato --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="type" class="block text-sm font-medium text-slate-300">Tipo <span class="text-rose-400">*</span></label>
        <select id="type" name="type"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('type') border-rose-500 @enderror">
            @foreach($types as $value => $label)
                <option value="{{ $value }}"
                    {{ old('type', $championship->type ?? 'league') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('type')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="legs" class="block text-sm font-medium text-slate-300">Mãos <span class="text-rose-400">*</span></label>
        <select id="legs" name="legs"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('legs') border-rose-500 @enderror">
            @foreach($legs as $value => $label)
                <option value="{{ $value }}"
                    {{ old('legs', $championship->legs ?? 'double') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('legs')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>
</div>

{{-- Times e vagas --}}
<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="teams_count" class="block text-sm font-medium text-slate-300">Nº de times <span class="text-rose-400">*</span></label>
        <input type="number" id="teams_count" name="teams_count"
            value="{{ old('teams_count', $championship->teams_count ?? 20) }}"
            min="2" max="64"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('teams_count') border-rose-500 @enderror">
        @error('teams_count')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="promotion_spots" class="block text-sm font-medium text-slate-300">Vagas de acesso</label>
        <input type="number" id="promotion_spots" name="promotion_spots"
            value="{{ old('promotion_spots', $championship->promotion_spots ?? '') }}"
            min="1" max="32" placeholder="—"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('promotion_spots') border-rose-500 @enderror">
        @error('promotion_spots')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="relegation_spots" class="block text-sm font-medium text-slate-300">Vagas de rebaixamento</label>
        <input type="number" id="relegation_spots" name="relegation_spots"
            value="{{ old('relegation_spots', $championship->relegation_spots ?? '') }}"
            min="1" max="32" placeholder="—"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('relegation_spots') border-rose-500 @enderror">
        @error('relegation_spots')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>
</div>
