<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(auth()->user()->role === 'seller' ? route('seller.dashboard') : route('catalog.index'));
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::middleware('auth')->group(function () {
    // Katalog & Produk (Pembeli)
    Route::get('/katalog', [ProductController::class, 'index'])->name('catalog.index');
    Route::get('/produk/{product}', [ProductController::class, 'show'])->name('products.show');

    // Checkout
    Route::get('/checkout/ringkasan', [TransactionController::class, 'summary'])->name('checkout.summary');
    Route::post('/checkout', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/pesanan/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/pesanan-saya', [TransactionController::class, 'index'])->name('transactions.index');

    // AI Chat (Inertia page + JSON API di bawahnya)
    Route::get('/tanya-ai', [AiChatController::class, 'index'])->name('ai-chat.index');
    Route::get('/ai-chat/{chat}', [AiChatController::class, 'show']);
    Route::post('/ai-chat/send', [AiChatController::class, 'send']);
    Route::get('/ai-chat/search', [AiChatController::class, 'search']);

    // Seller
    Route::middleware('can:seller')->prefix('toko')->group(function () {
        Route::get('/dashboard', [ProductController::class, 'myProducts'])->name('seller.dashboard');
        Route::get('/tambah-item', [ProductController::class, 'create'])->name('seller.products.create');
        Route::post('/tambah-item', [ProductController::class, 'store'])->name('seller.products.store');
        Route::delete('/produk/{product}', [ProductController::class, 'destroy'])->name('seller.products.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/checkout/prepare', [TransactionController::class, 'prepare'])->name('checkout.prepare');
});

require __DIR__.'/auth.php';