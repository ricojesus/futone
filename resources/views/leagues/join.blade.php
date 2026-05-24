<x-app-layout>
    {{-- Header --}}
    <div class="border-b border-slate-800 bg-slate-900">
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 mb-1">
                <a href="{{ route('dashboard') }}" class="text-slate-500 hover:text-slate-300 transition text-sm">Dashboard</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400 text-sm">Entrar numa Liga</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white">Entrar numa Liga</h1>
            <p class="mt-1 text-sm text-slate-400">Insira um código de convite ou escolha uma liga pública abaixo.</p>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8 space-y-10">

        {{-- Código de convite --}}
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-8">
            <div class="mx-auto max-w-md text-center">
                <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-400 ring-1 ring-sky-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z" /></svg>
                </div>
                <h2 class="mb-1 text-lg font-bold text-white">Tenho um código</h2>
                <p class="mb-6 text-sm text-slate-400">Digite o código de convite que você recebeu.</p>

                <form action="{{ route('leagues.join.store') }}" method="POST">
                    @csrf
                    <div class="flex gap-2">
                        <input type="text" name="invite_code"
                            value="{{ old('invite_code') }}"
                            placeholder="Ex: ABCD1234"
                            class="flex-1 rounded-xl border border-slate-600 bg-slate-800 px-4 py-3 text-center text-lg font-bold tracking-[0.2em] uppercase text-white placeholder-slate-600 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                            maxlength="12" autocomplete="off" />
                        <button type="submit"
                            class="shrink-0 rounded-xl bg-sky-500 px-5 py-3 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-sky-400 active:scale-95">
                            Entrar
                        </button>
                    </div>
                    @error('invite_code')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </form>
            </div>
        </div>

        {{-- Ligas públicas --}}
        <div>
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">
                Ligas Públicas Disponíveis
                <span class="ml-1 font-normal text-slate-500">({{ $publicLeagues->count() }})</span>
            </h2>

            @if ($publicLeagues->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-6 py-12 text-center">
                    <p class="text-slate-500">Nenhuma liga pública aguardando participantes no momento.</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($publicLeagues as $league)
                        @php $competitionCount = $league->competitions->count() @endphp
                        <div class="flex flex-col rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-emerald-500/30">
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="font-bold text-white">{{ $league->name }}</h3>
                                    <p class="text-sm text-slate-400">
                                        {{ $competitionCount }} {{ $competitionCount === 1 ? 'competição' : 'competições' }}
                                        @if ($league->season)
                                            · Temporada {{ $league->season }}
                                        @endif
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-400">Aguardando</span>
                            </div>

                            <div class="mb-4 flex items-center gap-4 text-xs text-slate-500">
                                <span>Criada por <strong class="text-slate-400">{{ $league->owner->name }}</strong></span>
                            </div>

                            <div class="mt-auto">
                                <a href="{{ route('leagues.show', $league) }}"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                                    Ver Liga
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
