{{-- Name --}}
<div>
    <label for="name" class="block text-sm font-medium text-slate-300">Nome <span class="text-rose-400">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $coach->name ?? '') }}"
        class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white placeholder-slate-500
               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
               @error('name') border-rose-500 @enderror"
        placeholder="Nome do treinador">
    @error('name')
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>

{{-- Country --}}
<div>
    <label for="country_id" class="block text-sm font-medium text-slate-300">País</label>
    <select id="country_id" name="country_id"
        class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white
               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500
               @error('country_id') border-rose-500 @enderror">
        <option value="">— Sem país —</option>
        @foreach($countries as $country)
            <option value="{{ $country->id }}"
                {{ old('country_id', $coach->country_id ?? '') === $country->id ? 'selected' : '' }}>
                {{ $country->name }}
            </option>
        @endforeach
    </select>
    @error('country_id')
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>
