<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Futone') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased" style="font-family: 'Outfit', sans-serif;">
        <main class="relative min-h-screen overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ asset('images/backgrounds/background_stadium.png') }}');"></div>
            <div class="absolute inset-0 bg-slate-950/60"></div>
            <div class="absolute inset-0 bg-[linear-gradient(120deg,rgba(2,6,23,0.9),rgba(2,132,199,0.28))]"></div>

            <div class="relative mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-8 sm:px-6 lg:px-8">
                <div class="w-full max-w-4xl rounded-3xl border border-slate-700 bg-slate-950/95 p-6 shadow-2xl sm:p-10 lg:p-12">
                    <div class="mb-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:gap-5">
                        <img src="{{ asset('images/logos/futone.png') }}" width="1000" alt="Logo Futone" class="h-20 w-20 shrink-0 rounded-md bg-white/85 p-1 shadow" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-200">Futone</p>
                            <h1 class="text-3xl font-bold text-white sm:text-4xl">Gerencie, dispute e conquiste sua liga.</h1>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 sm:p-6">
                        <p class="text-sm leading-relaxed text-slate-100 sm:text-base">
                            Futone e um jogo no estilo futmanager com criacao de ligas online, partidas multiplayer em tempo real contra outros jogadores e confrontos contra times controlados pelo computador.
                        </p>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-500 px-6 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-white transition hover:bg-emerald-400 sm:w-auto">
                            Entrar no Futone
                        </a>

                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-white/40 px-6 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-white transition hover:bg-white/10 sm:w-auto">
                                Acessar dashboard
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>
