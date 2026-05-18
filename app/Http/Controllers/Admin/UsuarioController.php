<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(): View
    {
        $usuarios = User::orderBy('name')->paginate(20);

        return view('admin.usuarios.index', compact('usuarios'));
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $request->validate([
            'type' => ['required', 'in:jogador,administrador'],
        ]);

        $usuario->update(['type' => $request->type]);

        return back()->with('success', 'Perfil do usuário atualizado.');
    }
}
