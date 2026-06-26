<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// ===== GUEST ROUTES (Sign Up & Login) =====
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Root URL: kalau sudah login -> dashboard, kalau belum -> halaman register
// (sesuai requirement: "Mengarahkan pengguna baru ke halaman Sign-Up terlebih dahulu")
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('register');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ===== AUTHENTICATED ROUTES =====
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [CharacterController::class, 'index'])->name('dashboard');

    // CRUD Karakter
    Route::get('/characters/create', [CharacterController::class, 'create'])->name('characters.create');
    Route::post('/characters', [CharacterController::class, 'store'])->name('characters.store');
    Route::get('/characters/{character}/edit', [CharacterController::class, 'edit'])->name('characters.edit');
    Route::put('/characters/{character}', [CharacterController::class, 'update'])->name('characters.update');
    Route::delete('/characters/{character}', [CharacterController::class, 'destroy'])->name('characters.destroy');

    // Ruang Chat
    Route::get('/chat/{character}', [ChatController::class, 'show'])->name('chat.show');

    // Rate limit 20 pesan/menit per user supaya kuota gratis Gemini
    // (10-15 RPM di tier gratis) tidak cepat habis akibat spam klik.
    Route::post('/chat/{chat}/message', [ChatController::class, 'sendMessage'])
        ->middleware('throttle:20,1')
        ->name('chat.message');
});
