<x-guest-layout>
    <!-- Page Content -->
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-100">
        <div class="w-full max-w-md">
            <div class="bg-white shadow-md rounded-lg p-8">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Futone</h1>
                    <p class="text-gray-600 mt-2">Bem-vindo ao Futone</p>
                </div>

                <div class="space-y-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="block w-full bg-indigo-600 text-white text-center py-2 rounded-lg hover:bg-indigo-700 transition">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="block w-full bg-indigo-600 text-white text-center py-2 rounded-lg hover:bg-indigo-700 transition">
                                Login
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="block w-full bg-gray-600 text-white text-center py-2 rounded-lg hover:bg-gray-700 transition">
                                    Registrar
                                </a>
                            @endif
                        @endauth
                    @endif
                </div>
            </div>

            <p class="text-center text-gray-600 text-sm mt-8">
                © 2026 Futone. Todos os direitos reservados.
            </p>
        </div>
    </div>
</x-guest-layout>
