{{-- Name --}}
<div>
    <label for="name" class="block text-sm font-medium text-slate-300">Nome <span class="text-rose-400">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $state->name ?? '') }}"
        class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white placeholder-slate-500
               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
               @error('name') border-rose-500 @enderror"
        placeholder="Ex: São Paulo">
    @error('name')
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>

{{-- Code + Country --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="code" class="block text-sm font-medium text-slate-300">Sigla <span class="text-rose-400">*</span></label>
        <input type="text" id="code" name="code" value="{{ old('code', $state->code ?? '') }}"
            class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white placeholder-slate-500 uppercase
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
                   @error('code') border-rose-500 @enderror"
            placeholder="Ex: SP" maxlength="10">
        @error('code')
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
                    {{ old('country_id', $state->country_id ?? '') === $country->id ? 'selected' : '' }}>
                    {{ $country->flag }} {{ $country->name }}
                </option>
            @endforeach
        </select>
        @error('country_id')
            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
        @enderror
    </div>
</div>
