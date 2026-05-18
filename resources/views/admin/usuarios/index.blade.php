<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Usuários') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-mail</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perfil</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($usuarios as $usuario)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $usuario->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $usuario->email }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                        {{ $usuario->isAdmin() ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ ucfirst($usuario->type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="type" onchange="this.form.submit()"
                                            class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="jogador" {{ $usuario->type === 'jogador' ? 'selected' : '' }}>Jogador</option>
                                            <option value="administrador" {{ $usuario->type === 'administrador' ? 'selected' : '' }}>Administrador</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="px-6 py-4">
                    {{ $usuarios->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
