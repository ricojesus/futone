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

{{-- Photo --}}
<div>
    <label for="photo" class="block text-sm font-medium text-slate-300">Foto</label>
    @if(!empty($coach->photo))
        <div class="mt-2 mb-3">
            <img src="{{ Storage::url($coach->photo) }}" alt="{{ $coach->name }}"
                class="h-20 w-20 rounded-full object-cover ring-2 ring-slate-700">
        </div>
    @endif
    <input type="file" id="photo" name="photo" accept="image/*"
        class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300
               file:mr-3 file:rounded-md file:border-0 file:bg-emerald-500/10 file:px-3 file:py-1 file:text-sm file:font-medium file:text-emerald-400
               hover:file:bg-emerald-500/20
               @error('photo') border-rose-500 @enderror">
    <p class="mt-1 text-xs text-slate-500">JPG, PNG ou GIF · máx. 2 MB</p>
    @error('photo')
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>
