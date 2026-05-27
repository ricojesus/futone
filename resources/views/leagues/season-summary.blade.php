<x-app-layout>
    @if (session('success'))
        <div class="border-b border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400 text-center">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="border-b border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400 text-center">{{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="relative overflow-hidden border-b border-slate-800 bg-slate-900">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(234,179,8,0.08),transparent_60%)]"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-3 flex items-center gap-2 text-sm">
                <a href="{{ route('leagues.index') }}" class="text-slate-500 hover:text-slate-300 transition">Minhas Ligas</a>
                <span class="text-slate-700">/</span>
                <a href="{{ route('leagues.show', $league) }}" class="text-slate-500 hover:text-slate-300 transition">{{ $league->name }}</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400">Resumo da Temporada</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white sm:text-3xl">
                🏆 Fim de Temporada — {{ $league->name }} {{ $league->season }}
            </h1>
            <p class="mt-2 text-slate-400">Veja os campeões, os promovidos e os rebaixados antes de iniciar a temporada {{ $nextYear }}.</p>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 space-y-16">

        {{-- ── NACIONAL ─────────────────────────────────────────────────────── --}}
        @if ($transitions['national'])
            @php $block = $transitions['national']; @endphp
            <section>
                <h2 class="mb-6 text-lg font-bold uppercase tracking-widest text-slate-300 border-b border-slate-800 pb-3">
                    🇧🇷 Nacional
                </h2>
                @include('leagues._season-block', ['block' => $block, 'typeLabel' => 'Série A', 'secondLabel' => 'Série B'])
            </section>
        @endif

        {{-- ── ESTADUAIS ────────────────────────────────────────────────────── --}}
        @foreach ($transitions['state'] as $stateId => $block)
            @php $stateModel = $block['state'] ?? null; @endphp
            <section>
                <h2 class="mb-6 text-lg font-bold uppercase tracking-widest text-slate-300 border-b border-slate-800 pb-3">
                    🏟 Estadual{{ $stateModel ? ' — ' . $stateModel->name . ' (' . $stateModel->code . ')' : '' }}
                </h2>
                @include('leagues._season-block', ['block' => $block, 'typeLabel' => 'A1', 'secondLabel' => 'A2'])
            </section>
        @endforeach

        {{-- ── CTA AVANÇAR TEMPORADA ────────────────────────────────────────── --}}
        @if ($isOwner)
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-8 text-center">
                <p class="mb-2 text-slate-300 text-lg font-semibold">Pronto para a próxima temporada?</p>
                <p class="mb-6 text-slate-500 text-sm">Isso irá criar as novas competições com promoções e rebaixamentos aplicados.</p>
                <form method="POST" action="{{ route('leagues.advance-season', $league) }}">
                    @csrf
                    <button type="submit"
                        onclick="return confirm('Confirma o início da temporada {{ $nextYear }}?')"
                        class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500 px-8 py-4 text-base font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95 w-full justify-center max-w-sm mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        Iniciar Temporada {{ $nextYear }}
                    </button>
                </form>
            </div>
        @endif

    </div>
</x-app-layout>
