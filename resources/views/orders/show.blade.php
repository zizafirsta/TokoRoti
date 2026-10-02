<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Transaksi: ') . $order->invoice_no }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="bg-white rounded-lg shadow-md p-6 space-y-6">
            <div class="flex justify-between items-center border-b pb-4">
                <div>
                    <p class="text-sm text-gray-500">Tanggal Transaksi</p>
                    <p class="font-semibold">{{ $order->created_at->locale('id')->translatedFormat('d F Y, H:i') }}</p>
                </div>
                <div>
                    <span class="text-sm font-semibold">Status: </span>
                    <x-order-status-badge :status="$order->status" />
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-800 mb-2">Informasi Pesanan</h3>
                <p class="text-gray-600"><strong>Layanan:</strong> {{ $order->fulfillment_type === 'delivery' ? 'Dikirim (Delivery)' : 'Ambil Sendiri (Pickup)' }}</p>
                @if($order->note)
                    <p class="text-gray-600"><strong>Keterangan:</strong> {{ $order->note }}</p>
                @endif
            </div>

            @php $payment = $order->payment; @endphp
            @if($payment)
                <div class="border-t pt-4 space-y-3">
                    <h3 class="font-bold text-gray-800">Pembayaran</h3>
                    <div class="flex flex-wrap items-center gap-3 text-gray-600">
                        <span><strong>Metode:</strong> {{ $payment->method_label }}</span>
                        <x-payment-status-badge :payment="$payment" />
                    </div>

                    @if($payment->paid_at)
                        <p class="text-sm text-gray-500">Dibayar pada {{ $payment->paid_at->locale('id')->translatedFormat('d F Y, H:i') }}</p>
                    @endif

                    @if($payment->proof)
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Bukti pembayaran Anda:</p>
                            <a href="{{ route('orders.proof', $order->id) }}" target="_blank">
                                <img src="{{ route('orders.proof', $order->id) }}" alt="Bukti pembayaran" class="max-h-48 rounded-lg border">
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            <div class="border-t pt-4">
                <h3 class="font-bold text-gray-800 mb-4">Item Pesanan</h3>
                <ul class="divide-y">
                    @foreach($order->items as $item)
                        <li class="py-2 flex justify-between">
                            <span>{{ $item->product?->name ?? 'Produk dihapus' }} (x{{ $item->quantity }})</span>
                            <span class="font-semibold">Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="border-t pt-4 space-y-1">
                <h3 class="font-bold text-gray-800 mb-2">Ringkasan Pembayaran</h3>
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($order->shipping_fee > 0)
                    <div class="flex justify-between text-gray-600">
                        <span>Ongkos Kirim</span>
                        <span>Rp {{ number_format($order->shipping_fee, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if($order->discount > 0)
                    <div class="flex justify-between text-gray-600">
                        <span>Diskon</span>
                        <span>- Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-xl font-extrabold text-amber-700 pt-2">
                    <span>Total Tagihan:</span>
                    <span>Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="border-t pt-4 flex justify-between">
                <a href="{{ route('orders.index') }}" class="text-gray-600 font-semibold hover:underline">&larr; Kembali ke Riwayat</a>
                @if($order->status == 'pending')
                    <div class="flex items-center gap-4">
                        @unless($payment?->proof)
                            <x-cancel-order-button :order="$order" />
                        @endunless
                        <a href="{{ route('checkout.payment', $order->id) }}" class="bg-amber-600 text-white px-6 py-2 rounded-md font-bold hover:bg-amber-700">
                            {{ $payment?->needsProof() ? ($payment->proof ? 'Lihat / Ganti Bukti' : 'Bayar Sekarang') : 'Lihat Petunjuk Bayar' }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
