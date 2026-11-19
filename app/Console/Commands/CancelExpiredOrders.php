<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class CancelExpiredOrders extends Command
{
    protected $signature = 'orders:cancel-expired';

    protected $description = 'Batalkan pesanan yang belum dibayar melewati batas waktu dan kembalikan stok';

    public function handle(): int
    {
        $hours = (int) config('toko.unpaid_expiry_hours', 24);

        // Pesanan yang sudah unggah bukti atau bayar di tempat tidak ikut dibatalkan otomatis.
        $orders = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subHours($hours))
            ->whereDoesntHave('payment', function ($query) {
                $query->whereNotNull('proof')->orWhere('method', 'cash');
            })
            ->get();

        foreach ($orders as $order) {
            $order->changeStatus('cancelled');
        }

        $this->info($orders->count() . ' pesanan kedaluwarsa dibatalkan.');

        return self::SUCCESS;
    }
}
