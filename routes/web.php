<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\StockController as AdminStockController;
use App\Http\Controllers\Admin\PromoController as AdminPromoController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;

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
    // FS-001: dashboard admin (+ peringatan stok menipis, FS-005)
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // FS-004: kelola produk
    Route::patch('products/{product}/toggle-active', [AdminProductController::class, 'toggleActive'])->name('products.toggle-active');
    Route::resource('products', AdminProductController::class);

    // FS-005: kelola stok
    Route::get('stock', [AdminStockController::class, 'index'])->name('stock.index');
    Route::post('stock/{product}', [AdminStockController::class, 'adjust'])->name('stock.adjust');

    // FS-006: update status pesanan
    Route::resource('orders', AdminOrderController::class)->only(['index', 'show', 'update']);

    // FS-007: kelola promo
    Route::patch('promos/{promo}/toggle', [AdminPromoController::class, 'toggle'])->name('promos.toggle');
    Route::resource('promos', AdminPromoController::class)->except(['show', 'destroy']);

    // FS-008: laporan penjualan
    Route::get('reports/sales', [AdminReportController::class, 'sales'])->name('reports.sales');
});

require __DIR__.'/auth.php';
