<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;

/*
|--------------------------------------------------------------------------
| 1. Route Publik (Tanpa Autentikasi)
|--------------------------------------------------------------------------
*/
Route::get('/', [ShopController::class, 'index'])->name('shop.index');
Route::get('/product/{id}', [ShopController::class, 'show'])->name('shop.show');


/*
|--------------------------------------------------------------------------
| 2. Route Pelanggan (Wajib Login)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [ShopController::class, 'dashboard'])->name('dashboard');

    // Profil User
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Keranjang Belanja
    Route::prefix('cart')->name('cart.')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('index');
        Route::post('/add/{id}', [CartController::class, 'add'])->name('add');
        Route::patch('/update/{id}', [CartController::class, 'update'])->name('update');
        Route::delete('/remove/{id}', [CartController::class, 'remove'])->name('remove');
    });

    // Proses Checkout & Pembayaran
    Route::prefix('checkout')->name('checkout.')->group(function () {
        Route::get('/', [CheckoutController::class, 'index'])->name('index');
        Route::post('/process', [CheckoutController::class, 'process'])->name('process');
        Route::get('/payment/{order}', [CheckoutController::class, 'payment'])->name('payment');
        Route::post('/payment/{order}/proof', [CheckoutController::class, 'uploadProof'])->name('proof');
    });

    // Pesanan Saya
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::get('/{order}/proof', [OrderController::class, 'proof'])->name('proof');
        Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
    });

});


/*
|--------------------------------------------------------------------------
| 3. Route Khusus Admin (Role Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('products', AdminProductController::class);
    Route::resource('orders', AdminOrderController::class)->only(['index', 'show', 'update']);
});

require __DIR__.'/auth.php';
