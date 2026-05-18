<x-app-layout>
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6 lg:px-8">

        <div class="mb-8">
            <a href="{{ route('admin.countries') }}"
                class="mb-4 inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-300 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Voltar para Países
            </a>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração</p>
            <h1 class="text-2xl font-extrabold text-white">Novo País</h1>
        </div>

        <form method="POST" action="{{ route('admin.countries.store') }}"
            class="rounded-2xl border border-slate-700 bg-slate-900 p-6 space-y-5">
            @csrf
            @include('admin.countries._form')

            <div class="flex items-center justify-end gap-3 border-t border-slate-800 pt-5">
                <a href="{{ route('admin.countries') }}"
                    class="rounded-xl border border-slate-700 px-5 py-2.5 text-sm font-medium text-slate-400 transition hover:bg-slate-800 hover:text-white">
                    Cancelar
                </a>
                <button type="submit"
                    class="rounded-xl bg-emerald-500 px-6 py-2.5 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-emerald-400 active:scale-95">
                    Salvar
                </button>
            </div>
        </form>

    </div>
</x-app-layout>
