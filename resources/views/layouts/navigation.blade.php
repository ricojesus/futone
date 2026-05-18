<header class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/90 backdrop-blur-sm" x-data="{ open: false, userOpen: false }">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">

            {{-- Logo --}}
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 shrink-0">
                <img src="{{ asset('images/logos/futone.png') }}" alt="Futone" class="h-9 w-auto" />
            </a>

            {{-- Nav links (desktop) --}}
            <nav class="hidden sm:flex items-center gap-1">
                <a href="{{ route('dashboard') }}"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition
                        {{ request()->routeIs('dashboard') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                    Dashboard
                </a>

                @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.*') ? 'bg-violet-500/20 text-violet-300' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Admin
                    </a>
                    <a href="{{ route('admin.users') }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.users') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Usuários
                    </a>
                    <a href="{{ route('admin.players') }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.players*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Jogadores
                    </a>
                    <a href="{{ route('admin.teams') }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.teams*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Times
                    </a>
                    <a href="{{ route('admin.championships') }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.championships*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Campeonatos
                    </a>
                    <a href="{{ route('admin.states') }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.states*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Estados
                    </a>
                    <a href="{{ route('admin.countries') }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.countries*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Países
                    </a>
                    <a href="{{ route('admin.coaches') }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.coaches*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Treinadores
                    </a>
                @endif
            </nav>

            {{-- User profile (desktop) --}}
            <div class="hidden sm:flex items-center gap-3 relative">
                <button @click="userOpen = !userOpen" @click.outside="userOpen = false"
                    class="flex items-center gap-2.5 rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold uppercase text-white">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </span>
                    <span class="max-w-[120px] truncate">{{ Auth::user()->name }}</span>
                    <svg class="h-4 w-4 text-slate-400 transition" :class="userOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-show="userOpen" x-transition
                    class="absolute right-0 top-12 w-52 rounded-xl border border-slate-700 bg-slate-900 py-1 shadow-xl">
                    <div class="border-b border-slate-800 px-4 py-3">
                        <p class="text-xs text-slate-400">Conectado como</p>
                        <p class="truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ Auth::user()->email }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                        Meu Perfil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" /></svg>
                            Sair
                        </button>
                    </form>
                </div>
            </div>

            {{-- Mobile hamburger --}}
            <button @click="open = !open" class="sm:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="open" x-transition class="sm:hidden border-t border-slate-800 bg-slate-950 px-4 py-3 space-y-1">
        <a href="{{ route('dashboard') }}"
            class="block px-3 py-2 rounded-lg text-sm font-medium
                {{ request()->routeIs('dashboard') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            Dashboard
        </a>

        @if(Auth::user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-violet-300 hover:text-white hover:bg-slate-800">Admin</a>
            <a href="{{ route('admin.users') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800">Usuários</a>
            <a href="{{ route('admin.players') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800">Jogadores</a>
            <a href="{{ route('admin.teams') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800">Times</a>
            <a href="{{ route('admin.championships') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800">Campeonatos</a>
            <a href="{{ route('admin.states') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800">Estados</a>
            <a href="{{ route('admin.countries') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800">Países</a>
            <a href="{{ route('admin.coaches') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800">Treinadores</a>
        @endif

        <div class="border-t border-slate-800 pt-3 mt-3">
            <div class="flex items-center gap-3 px-3 py-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-sm font-bold uppercase text-white">
                    {{ substr(Auth::user()->name, 0, 1) }}
                </span>
                <div>
                    <p class="text-sm font-medium text-white">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-slate-500">{{ Auth::user()->email }}</p>
                </div>
            </div>
            <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-slate-800">Meu Perfil</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full text-left px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-slate-800">
                    Sair
                </button>
            </form>
        </div>
    </div>
</header>
