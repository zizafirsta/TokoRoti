<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Kelola Transaksi Pelanggan') }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="p-3">No. Order</th>
                        <th class="p-3">Pelanggan</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Pembayaran</th>
                        <th class="p-3">Tanggal</th>
                        <th class="p-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-b">
                            <td class="p-3 font-semibold">{{ $order->invoice_no }}</td>
                            <td class="p-3">{{ $order->user?->name ?? '-' }} @if($order->user)({{ $order->user->email }})@endif</td>
                            <td class="p-3 font-bold text-amber-700">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                            <td class="p-3">
                                <x-order-status-badge :status="$order->status" />
                            </td>
                            <td class="p-3 text-sm">
                                <span class="block text-gray-600">{{ $order->payment?->method_label ?? '-' }}</span>
                                <x-payment-status-badge :payment="$order->payment" class="mt-1" />
                            </td>
                            <td class="p-3 text-sm text-gray-500">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            <td class="p-3 text-center">
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="bg-indigo-600 text-white text-xs px-3 py-1 rounded font-semibold hover:bg-indigo-700">Kelola</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-center text-gray-500">Belum ada transaksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
