<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan penjualan (FS-008).
 *
 * Total penjualan, jumlah order, dan produk terlaris semuanya dihitung dari
 * SATU basis query yang sama (orders berstatus SALES_STATUSES dalam rentang tanggal),
 * sehingga angka di laporan selalu konsisten dengan data pesanan (RQ-030).
 */
class SalesReport
{
    /** @return array{start: Carbon, end: Carbon, total_sales: float, order_count: int, top_products: \Illuminate\Support\Collection, is_empty: bool} */
    public static function generate(Carbon $start, Carbon $end, int $topLimit = 5): array
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->endOfDay();

        $orders = Order::query()
            ->whereIn('status', Order::SALES_STATUSES)
            ->whereBetween('created_at', [$from, $to]);

        $orderCount = (clone $orders)->count();
        $totalSales = (float) (clone $orders)->sum('total');

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', Order::SALES_STATUSES)
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.id as product_id, products.name as name, SUM(order_items.quantity) as qty_sold, SUM(order_items.quantity * order_items.price) as revenue')
            ->orderByDesc('qty_sold')
            ->orderBy('products.name')
            ->limit($topLimit)
            ->get();

        return [
            'start'        => $from,
            'end'          => $to,
            'total_sales'  => $totalSales,
            'order_count'  => $orderCount,
            'top_products' => $topProducts,
            'is_empty'     => $orderCount === 0,
        ];
    }
}
