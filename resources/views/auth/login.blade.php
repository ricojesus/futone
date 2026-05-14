<x-guest-layout>
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-600">Acesso a sua conta</p>
        <h2 class="mt-2 text-3xl font-bold text-slate-900">Entrar no Futone</h2>
        <p class="mt-2 text-sm text-slate-600">Gerencie seu clube, participe de ligas e jogue partidas em tempo real.</p>
    </div>

    <x-auth-session-status class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" class="text-sm font-medium text-slate-700" />
            <x-text-input id="email" class="mt-2 block w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="voce@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Senha')" class="text-sm font-medium text-slate-700" />

            <x-text-input id="password" class="mt-2 block w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500"
                            type="password"
                            name="password"
                            required autocomplete="current-password"
                            placeholder="Sua senha" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">{{ __('Lembrar de mim') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-emerald-700 transition hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" href="{{ route('password.request') }}">
                    {{ __('Esqueceu a senha?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-white transition hover:bg-slate-800 focus:bg-slate-800 focus:ring-slate-700 active:bg-slate-950">
                {{ __('Entrar') }}
            </x-primary-button>

        <p class="text-center text-xs text-slate-500">Ao entrar, voce aceita as regras da liga e o fair play competitivo.</p>
    </form>
</x-guest-layout>
