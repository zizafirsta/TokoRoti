<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    // Tampilkan seluruh transaksi pelanggan
    public function index()
    {
        $orders = Order::with(['user', 'payment'])->latest()->paginate(20);

        return view('admin.orders.index', compact('orders'));
    }

    // Tampilkan detail transaksi
    public function show(Order $order)
    {
        $order->load(['user', 'items.product', 'payment', 'statusLogs']);

        return view('admin.orders.show', compact('order'));
    }

    // Update status transaksi secara manual
    public function update(Request $request, Order $order)
    {
        // Sebelumnya memakai "failed" yang TIDAK ada di enum database (error 500),
        // dan status processing/ready/shipped/completed tidak bisa dipilih.
        $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUS_LABELS))],
        ]);

        if ($order->status === 'cancelled' && $request->status !== 'cancelled') {
            return redirect()->back()->with('error', 'Pesanan yang sudah dibatalkan tidak bisa diaktifkan kembali (stok sudah dikembalikan). Minta pelanggan membuat pesanan baru.');
        }

        $order->changeStatus($request->status, auth()->id());

        return redirect()->back()->with('success', 'Status transaksi berhasil diperbarui!');
    }
}
