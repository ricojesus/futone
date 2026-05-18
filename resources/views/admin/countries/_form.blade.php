{{-- Nome --}}
<div>
    <label class="mb-1.5 block text-sm font-medium text-slate-300">Nome <span class="text-red-400">*</span></label>
    <input type="text" name="name" value="{{ old('name', $country->name ?? '') }}" required
        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder-slate-500
               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
        placeholder="Ex: Brasil" />
    @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
</div>

{{-- Código + Bandeira --}}
<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-300">
            Código ISO <span class="text-red-400">*</span>
            <span class="ml-1 text-xs font-normal text-slate-500">Ex: BRA, POR</span>
        </label>
        <input type="text" name="code" value="{{ old('code', $country->code ?? '') }}" required maxlength="3"
            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 font-mono text-sm uppercase text-white placeholder-slate-500
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            placeholder="BRA" />
        @error('code') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-300">
            Bandeira
            <span class="ml-1 text-xs font-normal text-slate-500">Emoji do país</span>
        </label>
        <input type="text" name="flag" value="{{ old('flag', $country->flag ?? '') }}" maxlength="10"
            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-2xl text-white placeholder-slate-500
                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            placeholder="🇧🇷" />
        @error('flag') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>
</div>
