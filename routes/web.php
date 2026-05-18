<?php

use App\Http\Controllers\Admin\PaisController;
use App\Http\Controllers\Admin\TimeController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios');
    Route::patch('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');

    Route::get('/times', [TimeController::class, 'index'])->name('times');

    Route::get('/paises', [PaisController::class, 'index'])->name('paises');
});

require __DIR__.'/auth.php';
