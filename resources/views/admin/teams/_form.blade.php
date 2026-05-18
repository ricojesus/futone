{{-- Name --}}
<div>
    <label for="name" class="block text-sm font-medium text-slate-300">Nome <span class="text-rose-400">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $team->name ?? '') }}"
        class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white placeholder-slate-500
               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
               @error('name') border-rose-500 @enderror"
        placeholder="Ex: Flamengo">
    @error('name')
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>

{{-- State + Country --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="state_id" class="block text-sm font-medium text-slate-300">Estado</label>
        <select id="state_id" name="state_id"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('state_id') border-rose-500 @enderror">
            <option value="">— Sem estado —</option>
            @foreach($states as $state)
                <option value="{{ $state->id }}"
                    {{ old('state_id', $team->state_id ?? '') === $state->id ? 'selected' : '' }}>
                    {{ $state->name }} ({{ $state->code }})
                </option>
            @endforeach
        </select>
        @error('state_id')
            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
        @enderror
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
                    {{ old('country_id', $team->country_id ?? '') === $country->id ? 'selected' : '' }}>
                    {{ $country->name }}
                </option>
            @endforeach
        </select>
        @error('country_id')
            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
        @enderror
    </div>
</div>

{{-- Tolerance --}}
<div x-data="{ value: {{ old('tolerance', $team->tolerance ?? 50) }} }">
    <label for="tolerance" class="flex items-center justify-between text-sm font-medium text-slate-300">
        <span>Tolerância do clube <span class="text-rose-400">*</span></span>
        <span class="text-xs tabular-nums"
            :class="{
                'text-emerald-400': value >= 70,
                'text-amber-400':   value >= 40 && value < 70,
                'text-rose-400':    value < 40
            }">
            <span x-text="value"></span> / 100
        </span>
    </label>
    <input type="range" id="tolerance" name="tolerance"
        min="1" max="100" x-model="value"
        class="mt-2 w-full accent-emerald-500">
    <div class="mt-1 flex justify-between text-xs text-slate-500">
        <span>1 — Exigente (demite rápido)</span>
        <span>100 — Paciente</span>
    </div>
    @error('tolerance')
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>

{{-- Badge --}}
<div>
    <label for="badge" class="block text-sm font-medium text-slate-300">Escudo</label>
    @if(!empty($team->badge))
        <div class="mt-2 mb-3">
            <img src="{{ Storage::url($team->badge) }}" alt="{{ $team->name }}"
                class="h-16 w-16 rounded-lg object-contain bg-slate-800 ring-1 ring-slate-700 p-1">
        </div>
    @endif
    <input type="file" id="badge" name="badge" accept="image/*"
        class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300
               file:mr-3 file:rounded-md file:border-0 file:bg-emerald-500/10 file:px-3 file:py-1 file:text-sm file:font-medium file:text-emerald-400
               hover:file:bg-emerald-500/20
               @error('badge') border-rose-500 @enderror">
    <p class="mt-1 text-xs text-slate-500">JPG, PNG ou SVG · máx. 2 MB</p>
    @error('badge')
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>
