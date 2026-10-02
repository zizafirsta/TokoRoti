<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Riwayat Pesanan Saya') }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
            @if($orders->count() > 0)
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b text-left text-gray-600 bg-gray-50">
                            <th class="p-3">No. Transaksi</th>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">Total Bayar</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Pembayaran</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr class="border-b">
                                <td class="p-3 font-semibold">{{ $order->invoice_no }}</td>
                                <td class="p-3">{{ $order->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</td>
                                <td class="p-3 font-bold text-amber-700">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                                <td class="p-3">
                                    <x-order-status-badge :status="$order->status" />
                                </td>
                                <td class="p-3 text-sm">
                                    <span class="block text-gray-600">{{ $order->payment?->method_label ?? '-' }}</span>
                                    <x-payment-status-badge :payment="$order->payment" class="mt-1" />
                                </td>
                                <td class="p-3 text-center space-x-2">
                                    <a href="{{ route('orders.show', $order->id) }}" class="text-indigo-600 font-semibold hover:underline">Detail</a>
                                    @if($order->status == 'pending')
                                        <a href="{{ route('checkout.payment', $order->id) }}" class="bg-amber-600 text-white text-xs px-3 py-1 rounded hover:bg-amber-700">Cara Bayar</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="text-center py-12 text-gray-500">
                    <p>Anda belum memiliki riwayat transaksi.</p>
                    <a href="{{ route('shop.index') }}" class="mt-4 inline-block bg-amber-600 text-white px-6 py-2 rounded-md font-semibold">Mulai Belanja</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
