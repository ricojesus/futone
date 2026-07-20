<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Futone') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon-32.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logos/favicon-180.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="text-gray-900 antialiased" style="font-family: 'Outfit', sans-serif;">
        @if (request()->routeIs('login'))
            <div class="relative min-h-screen overflow-hidden bg-slate-950">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.35),_transparent_45%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.25),_transparent_40%)]"></div>
                <div class="pointer-events-none absolute -left-24 top-24 h-64 w-64 rounded-full bg-emerald-500/30 blur-3xl"></div>
                <div class="pointer-events-none absolute -right-20 bottom-10 h-72 w-72 rounded-full bg-cyan-400/20 blur-3xl"></div>

                <div class="relative mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-8 sm:px-6 lg:px-8">
                    <div class="grid w-full overflow-hidden rounded-3xl border border-white/10 bg-white/95 shadow-2xl shadow-black/50 backdrop-blur md:grid-cols-2">
                        <section class="relative hidden bg-gradient-to-br from-emerald-700 via-emerald-600 to-cyan-500 p-10 text-white md:flex md:flex-col md:justify-between">
                            <div class="space-y-6">
                                <img src="{{ asset('images/logos/futone.png') }}" alt="Futone" class="h-16 w-16 rounded-2xl bg-white/90 p-2 shadow-lg" />
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.32em] text-white/85">Futone</p>
                                    <h1 class="mt-3 text-4xl font-bold leading-tight">A liga comeca na estrategia.</h1>
                                    <p class="mt-4 max-w-sm text-sm text-white/90">Entre para competir em ligas online, enfrentar humanos em tempo real e testar sua equipe contra times da CPU.</p>
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <div class="rounded-2xl bg-white/15 p-4 backdrop-blur">
                                    <p class="text-xs uppercase tracking-[0.22em] text-white/75">Modo competitivo</p>
                                    <p class="mt-1 text-lg font-semibold">PvP em tempo real</p>
                                </div>
                                <div class="rounded-2xl bg-white/15 p-4 backdrop-blur">
                                    <p class="text-xs uppercase tracking-[0.22em] text-white/75">Modo treinamento</p>
                                    <p class="mt-1 text-lg font-semibold">Partidas contra CPU</p>
                                </div>
                            </div>
                        </section>

                        <section class="px-5 py-7 sm:px-8 sm:py-9 lg:px-10 lg:py-10">
                            <div class="mb-8 flex items-center gap-3 md:hidden">
                                <img src="{{ asset('images/logos/futone.png') }}" alt="Futone" class="h-12 w-12 rounded-xl bg-slate-100 p-2" />
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Futone</p>
                                    <p class="text-sm font-medium text-slate-700">Ligas online em tempo real</p>
                                </div>
                            </div>

                            {{ $slot }}
                        </section>
                    </div>
                </div>
            </div>
        @else
            <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-100">
                <div>
                    <a href="/">
                        <img src="{{ asset('images/logos/futone.png') }}" alt="Futone" class="w-16 h-16 object-contain" />
                    </a>
                </div>

                <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                    {{ $slot }}
                </div>
            </div>
        @endif
    </body>
</html>
