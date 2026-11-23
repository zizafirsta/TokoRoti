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

    // Update status transaksi: harus berurutan, pembatalan wajib beralasan, setiap perubahan dicatat
    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status'        => ['required', Rule::in(array_keys(Order::STATUS_LABELS))],
            // RQ-023: alasan wajib pada pembatalan
            'cancel_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'min:3', 'max:255'],
        ], [
            'status.required'           => 'Status wajib dipilih.',
            'status.in'                 => 'Status tidak valid.',
            'cancel_reason.required_if' => 'Alasan pembatalan wajib diisi.',
            'cancel_reason.min'         => 'Alasan pembatalan minimal 3 karakter.',
        ]);

        // RQ-022: tolak perubahan yang tidak sesuai urutan
        if (! $order->canTransitionTo($request->status)) {
            $next = $order->nextStatus();
            $hint = $next
                ? 'Status berikutnya yang diizinkan: ' . Order::STATUS_LABELS[$next] . ' atau Dibatalkan.'
                : 'Pesanan ini sudah berada di status akhir dan tidak bisa diubah lagi.';

            return redirect()->back()->with(
                'error',
                'Perubahan status dari "' . Order::STATUS_LABELS[$order->status] . '" ke "' . Order::STATUS_LABELS[$request->status] . '" tidak sesuai urutan. ' . $hint
            );
        }

        $reason = $request->status === 'cancelled' ? $request->cancel_reason : null;

        // RQ-024: changeStatus() mencatat setiap perubahan ke order_status_logs
        $order->changeStatus($request->status, auth()->id(), $reason);

        return redirect()->back()->with('success', 'Status transaksi berhasil diperbarui!');
    }
}
