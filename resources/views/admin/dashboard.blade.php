<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard Admin') }}</h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <x-flash />

        {{-- RQ-020: peringatan stok menipis --}}
        @if($lowStockProducts->isNotEmpty())
            <div id="low-stock-warning" class="bg-yellow-50 border border-yellow-400 text-yellow-800 rounded-lg p-4">
                <p class="font-bold">&#9888; Peringatan: {{ $lowStockProducts->count() }} produk stoknya menipis</p>
                <ul class="mt-2 text-sm list-disc list-inside">
                    @foreach($lowStockProducts as $product)
                        <li>
                            {{ $product->name }} &mdash; stok {{ $product->stock }} (batas minimum {{ $product->min_stock }})
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.stock.index') }}" class="inline-block mt-3 text-sm font-semibold underline">Kelola stok &rarr;</a>
            </div>
        @else
            <div class="bg-green-50 border border-green-300 text-green-800 rounded-lg p-4 text-sm">
                Semua stok produk masih aman.
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Menunggu Pembayaran</p>
                <p class="text-3xl font-extrabold text-amber-700">{{ $stats['pending_orders'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Pesanan Aktif</p>
                <p class="text-3xl font-extrabold text-amber-700">{{ $stats['active_orders'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Total Produk</p>
                <p class="text-3xl font-extrabold text-amber-700">{{ $stats['products'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-center text-sm font-semibold">
            <a href="{{ route('admin.products.index') }}" class="bg-indigo-600 text-white rounded-lg p-3 hover:bg-indigo-700">Kelola Produk</a>
            <a href="{{ route('admin.stock.index') }}" class="bg-indigo-600 text-white rounded-lg p-3 hover:bg-indigo-700">Kelola Stok</a>
            <a href="{{ route('admin.orders.index') }}" class="bg-indigo-600 text-white rounded-lg p-3 hover:bg-indigo-700">Kelola Transaksi</a>
            <a href="{{ route('admin.promos.index') }}" class="bg-indigo-600 text-white rounded-lg p-3 hover:bg-indigo-700">Kelola Promo</a>
            <a href="{{ route('admin.reports.sales') }}" class="bg-indigo-600 text-white rounded-lg p-3 hover:bg-indigo-700">Laporan Penjualan</a>
        </div>
    </div>
</x-app-layout>
