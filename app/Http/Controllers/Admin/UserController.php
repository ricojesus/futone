<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'type' => ['required', 'in:jogador,administrador'],
        ]);

        $user->update(['type' => $request->type]);

        return back()->with('success', 'Perfil do usuário atualizado.');
    }
}
