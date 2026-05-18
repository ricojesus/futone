<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-8">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração · Treinadores</p>
            <h1 class="text-2xl font-extrabold text-white">Editar Treinador</h1>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
            <form method="POST" action="{{ route('admin.coaches.update', $coach) }}" enctype="multipart/form-data"
                class="space-y-6">
                @csrf
                @method('PATCH')

                @include('admin.coaches._form')

                <div class="flex items-center justify-between border-t border-slate-800 pt-6">
                    {{-- Delete --}}
                    <form method="POST" action="{{ route('admin.coaches.destroy', $coach) }}"
                        onsubmit="return confirm('Remover este treinador?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="rounded-lg border border-rose-500/40 px-4 py-2 text-sm font-medium text-rose-400 transition hover:border-rose-500 hover:bg-rose-500/10">
                            Remover
                        </button>
                    </form>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.coaches') }}"
                            class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                            Cancelar
                        </a>
                        <button type="submit"
                            class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                            Salvar alterações
                        </button>
                    </div>
                </div>
            </form>
        </div>

    </div>
</x-app-layout>
