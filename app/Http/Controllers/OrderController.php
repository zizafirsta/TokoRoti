<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    // Tampilkan daftar transaksi milik customer yang sedang login
    public function index()
    {
        $orders = Order::with('payment')->where('user_id', auth()->id())->latest()->get();

        return view('orders.index', compact('orders'));
    }

    // Tampilkan rincian transaksi
    public function show(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $order->load(['items.product', 'payment']);

        return view('orders.show', compact('order'));
    }

    // Batalkan pesanan yang belum dibayar (stok dikembalikan otomatis)
    public function cancel(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status !== 'pending') {
            return redirect()->route('orders.show', $order->id)
                ->with('error', 'Hanya pesanan yang belum dibayar yang bisa dibatalkan.');
        }

        if ($order->payment?->proof) {
            return redirect()->route('orders.show', $order->id)
                ->with('error', 'Bukti pembayaran sudah dikirim dan sedang diverifikasi. Silakan hubungi toko untuk membatalkan.');
        }

        $order->changeStatus('cancelled', auth()->id());

        return redirect()->route('orders.show', $order->id)
            ->with('success', 'Pesanan berhasil dibatalkan.');
    }

    // Tampilkan bukti pembayaran (privat: hanya pemilik pesanan & admin)
    public function proof(Order $order)
    {
        $user = auth()->user();

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            abort(403);
        }

        $path = $order->payment?->proof;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
    }
}
