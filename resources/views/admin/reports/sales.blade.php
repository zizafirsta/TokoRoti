<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Laporan Penjualan') }}</h2>
    </x-slot>

    <div class="py-12 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <x-flash />

        <form method="GET" action="{{ route('admin.reports.sales') }}" class="bg-white rounded-lg shadow-md p-4 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-semibold">Dari Tanggal</label>
                <input type="date" name="start_date" value="{{ $report['start']->format('Y-m-d') }}" class="border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-sm font-semibold">Sampai Tanggal</label>
                <input type="date" name="end_date" value="{{ $report['end']->format('Y-m-d') }}" class="border-gray-300 rounded-md">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-indigo-700">Tampilkan</button>
        </form>

        @if($report['is_empty'])
            {{-- RQ-031 --}}
            <div id="report-empty" class="bg-gray-50 border border-gray-300 text-gray-600 rounded-lg p-6 text-center">
                Tidak ada data penjualan pada rentang tanggal yang dipilih
                ({{ $report['start']->format('d/m/Y') }} &ndash; {{ $report['end']->format('d/m/Y') }}).
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Total Penjualan</p>
                    <p id="report-total" class="text-3xl font-extrabold text-amber-700">Rp {{ number_format($report['total_sales'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Jumlah Order</p>
                    <p id="report-count" class="text-3xl font-extrabold text-amber-700">{{ $report['order_count'] }}</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
                <h3 class="font-bold text-gray-700 mb-3">Produk Terlaris</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="p-2">#</th>
                            <th class="p-2">Produk</th>
                            <th class="p-2">Terjual</th>
                            <th class="p-2">Pendapatan Produk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['top_products'] as $i => $row)
                            <tr class="border-b">
                                <td class="p-2">{{ $i + 1 }}</td>
                                <td class="p-2 font-semibold">{{ $row->name }}</td>
                                <td class="p-2">{{ $row->qty_sold }} pcs</td>
                                <td class="p-2">Rp {{ number_format($row->revenue, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-gray-500 mt-3">
                    Penjualan dihitung dari pesanan berstatus Dibayar, Diproses, Siap Diambil, Dikirim, dan Selesai
                    (pesanan Menunggu Pembayaran dan Dibatalkan tidak dihitung). Total penjualan sudah termasuk ongkos kirim.
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
