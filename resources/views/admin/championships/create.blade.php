<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-8">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração · Campeonatos</p>
            <h1 class="text-2xl font-extrabold text-white">Novo Campeonato</h1>
        </div>
        @if($errors->any())
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
            <form method="POST" action="{{ route('admin.championships.store') }}" class="space-y-6">
                @csrf
                @include('admin.championships._form')
                <div class="flex items-center justify-end gap-3 border-t border-slate-800 pt-6">
                    <a href="{{ route('admin.championships') }}"
                        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                        Cancelar
                    </a>
                    <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500">
                        Salvar campeonato
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
