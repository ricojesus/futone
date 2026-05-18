<x-app-layout>
    {{-- Hero --}}
    <div class="relative overflow-hidden border-b border-slate-800 bg-slate-900">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(139,92,246,0.10),transparent_60%)]"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-widest text-violet-400">Painel Administrativo</p>
            <h1 class="mt-1 text-3xl font-extrabold text-white sm:text-4xl">Visão geral do sistema</h1>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">

        {{-- Cards de resumo --}}
        <h2 class="mb-6 text-xs font-semibold uppercase tracking-widest text-slate-500">Totais cadastrados</h2>

        <div class="mb-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">

            {{-- Usuários --}}
            <a href="{{ route('admin.users') }}"
                class="flex items-center gap-5 rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-sky-500/50 hover:bg-slate-800/60">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-400 ring-1 ring-sky-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-white">{{ $stats['users'] }}</p>
                    <p class="text-sm text-slate-400">Usuários</p>
                </div>
            </a>

            {{-- Times --}}
            <a href="{{ route('admin.teams') }}"
                class="flex items-center gap-5 rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-violet-500/50 hover:bg-slate-800/60">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-400 ring-1 ring-violet-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-white">{{ $stats['teams'] }}</p>
                    <p class="text-sm text-slate-400">Times</p>
                </div>
            </a>

            {{-- Jogadores --}}
            <a href="{{ route('admin.players') }}"
                class="flex items-center gap-5 rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-emerald-500/50 hover:bg-slate-800/60">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-white">{{ $stats['players'] }}</p>
                    <p class="text-sm text-slate-400">Jogadores</p>
                </div>
            </a>

            {{-- Países --}}
            <a href="{{ route('admin.countries') }}"
                class="flex items-center gap-5 rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-amber-500/50 hover:bg-slate-800/60">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.96 8.96 0 0 0 .284 2.253" />
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-white">{{ $stats['countries'] }}</p>
                    <p class="text-sm text-slate-400">Países</p>
                </div>
            </a>

            {{-- Treinadores --}}
            <a href="{{ route('admin.coaches') }}"
                class="flex items-center gap-5 rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-rose-500/50 hover:bg-slate-800/60">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-500/10 text-rose-400 ring-1 ring-rose-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-white">{{ $stats['coaches'] }}</p>
                    <p class="text-sm text-slate-400">Treinadores</p>
                </div>
            </a>

        </div>

        {{-- Atalhos rápidos --}}
        <h2 class="mb-6 text-xs font-semibold uppercase tracking-widest text-slate-500">Ações rápidas</h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('admin.users') }}"
                class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3.5 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
                Gerenciar usuários
            </a>

            <a href="{{ route('admin.players.create') }}"
                class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3.5 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Novo jogador
            </a>

            <a href="{{ route('admin.teams') }}"
                class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3.5 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5" />
                </svg>
                Gerenciar times
            </a>

            <a href="{{ route('admin.teams.create') }}"
                class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3.5 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Novo time
            </a>

            <a href="{{ route('admin.countries') }}"
                class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3.5 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.96 8.96 0 0 0 .284 2.253" />
                </svg>
                Gerenciar países
            </a>

            <a href="{{ route('admin.coaches.create') }}"
                class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3.5 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Novo treinador
            </a>
        </div>

    </div>
</x-app-layout>
