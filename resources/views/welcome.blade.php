<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Futone') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon-32.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logos/favicon-180.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased" style="font-family: 'Outfit', sans-serif;">
        <main class="relative min-h-screen overflow-hidden">
            {{-- Background --}}
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ asset('images/backgrounds/background_stadium.png') }}');"></div>
            <div class="absolute inset-0 bg-slate-950/60"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(2,6,23,0.3)_0%,rgba(2,6,23,0.75)_55%,rgba(2,6,23,0.95)_100%)]"></div>

            {{-- Content --}}
            <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-16 sm:px-6 lg:px-8">

                {{-- Logo --}}
                <img
                    src="{{ asset('images/logos/futone_grande.png') }}"
                    alt="Logo Futone"
                    class="mb-6 h-28 w-auto drop-shadow-2xl sm:h-36 lg:h-44"
                />

                {{-- Slogan --}}
                <p class="mb-4 text-sm font-bold uppercase tracking-[0.2em] text-emerald-400 sm:text-base">
                    A nova geração dos jogos de gestão de futebol
                </p>

                {{-- Headline + Subtitle --}}
                <div class="mb-10 max-w-2xl rounded-2xl bg-slate-950/70 px-6 py-8 text-center backdrop-blur-sm sm:px-10">
                    <h1 class="mb-4 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl lg:text-6xl">
                        Gerencie, dispute e conquiste sua liga.
                    </h1>
                    <p class="text-base font-medium leading-relaxed text-white/90 sm:text-lg">
                        Futone é um jogo de gestão de times de futebol online, com criação de ligas
                        e partidas multiplayer em tempo real.
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex flex-col items-center gap-3 sm:flex-row">
                    @auth
                        <a href="{{ url('/dashboard') }}"
                            class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-8 py-3.5 text-sm font-bold uppercase tracking-widest text-white shadow-lg transition hover:bg-emerald-400 active:scale-95">
                            Acessar dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-8 py-3.5 text-sm font-bold uppercase tracking-widest text-white shadow-lg transition hover:bg-emerald-400 active:scale-95">
                            Entrar
                        </a>
                        <a href="{{ route('register') }}"
                            class="inline-flex items-center justify-center rounded-xl border border-white/30 px-8 py-3.5 text-sm font-bold uppercase tracking-widest text-white shadow-lg backdrop-blur-sm transition hover:bg-white/10 active:scale-95">
                            Criar conta
                        </a>
                    @endauth
                </div>

            </div>
        </main>
    </body>
</html>
