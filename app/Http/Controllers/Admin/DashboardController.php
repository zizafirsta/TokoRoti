<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    // RQ-001 & RQ-020: dashboard admin + peringatan stok menipis
    public function index()
    {
        $lowStockProducts = Product::lowStock()->orderBy('stock')->get();

        $stats = [
            'pending_orders' => Order::where('status', 'pending')->count(),
            'active_orders'  => Order::whereIn('status', ['paid', 'processing', 'ready', 'shipped'])->count(),
            'products'       => Product::count(),
        ];

        return view('admin.dashboard', compact('lowStockProducts', 'stats'));
    }
}
