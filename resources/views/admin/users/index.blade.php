<x-app-layout>
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

        {{-- Cabeçalho --}}
        <div class="mb-8">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Administração</p>
            <h1 class="text-2xl font-extrabold text-white">Usuários</h1>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Usuário</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">E-mail</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Perfil</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Alterar perfil</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach($users as $user)
                        <tr class="transition hover:bg-slate-800/40">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-700 text-sm font-bold text-slate-300">
                                        {{ substr($user->name, 0, 1) }}
                                    </span>
                                    <span class="text-sm font-semibold text-white">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-400">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium
                                    {{ $user->isAdmin() ? 'bg-violet-500/10 text-violet-400' : 'bg-sky-500/10 text-sky-400' }}">
                                    {{ $user->isAdmin() ? 'Administrador' : 'Jogador' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="type" onchange="this.form.submit()"
                                        class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm text-slate-300
                                               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                        <option value="jogador" {{ $user->type === 'jogador' ? 'selected' : '' }}>Jogador</option>
                                        <option value="administrador" {{ $user->type === 'administrador' ? 'selected' : '' }}>Administrador</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="border-t border-slate-800 px-6 py-4">
                {{ $users->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
