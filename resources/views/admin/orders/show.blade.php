<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Kelola Order: ') . $order->invoice_no }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="bg-white rounded-lg shadow-md p-6 space-y-6">

            <div class="grid grid-cols-2 gap-4 border-b pb-4">
                <div>
                    <h3 class="font-bold text-gray-700">Data Pemesan:</h3>
                    <p>{{ $order->user?->name ?? '-' }}</p>
                    <p class="text-sm text-gray-500">{{ $order->user?->email }}</p>
                    @if($order->user?->phone)
                        <p class="text-sm text-gray-500">HP: {{ $order->user->phone }}</p>
                    @endif
                </div>
                <div>
                    <h3 class="font-bold text-gray-700">
                        {{ $order->fulfillment_type === 'delivery' ? 'Dikirim (Delivery)' : 'Ambil Sendiri (Pickup)' }}
                    </h3>
                    <p class="text-gray-600 text-sm">{{ $order->note ?: '-' }}</p>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 mb-2">Item Pesanan</h3>
                <ul class="divide-y">
                    @foreach($order->items as $item)
                        <li class="py-2 flex justify-between">
                            <span>{{ $item->product?->name ?? 'Produk dihapus' }} (x{{ $item->quantity }})</span>
                            <span class="font-semibold">Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
                @if($order->shipping_fee > 0)
                    <p class="text-sm text-gray-500 mt-2">Ongkos kirim: Rp {{ number_format($order->shipping_fee, 0, ',', '.') }}</p>
                @endif
            </div>

            @php $payment = $order->payment; @endphp
            <div class="border rounded-lg p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-bold text-gray-700">Pembayaran</h3>
                    <x-payment-status-badge :payment="$payment" />
                </div>

                @if($payment)
                    <p class="text-sm text-gray-600"><strong>Metode:</strong> {{ $payment->method_label }}</p>
                    @if($payment->payer_name)
                        <p class="text-sm text-gray-600"><strong>Nama pengirim:</strong> {{ $payment->payer_name }}</p>
                    @endif
                    @if($payment->paid_at)
                        <p class="text-sm text-gray-600"><strong>Dibayar:</strong> {{ $payment->paid_at->format('d/m/Y H:i') }}</p>
                    @endif

                    @if($payment->proof)
                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Bukti pembayaran (diunggah {{ $payment->proof_uploaded_at?->format('d/m/Y H:i') }}):
                            </p>
                            <a href="{{ route('orders.proof', $order->id) }}" target="_blank">
                                <img src="{{ route('orders.proof', $order->id) }}" alt="Bukti pembayaran" class="max-h-96 rounded-lg border">
                            </a>
                        </div>
                    @elseif($payment->needsProof() && $order->status === 'pending')
                        <p class="text-sm text-yellow-700">Pelanggan belum mengunggah bukti pembayaran.</p>
                    @endif
                @else
                    <p class="text-sm text-gray-500">Belum ada data pembayaran.</p>
                @endif

                @if($order->status === 'pending')
                    <form action="{{ route('admin.orders.update', $order->id) }}" method="POST"
                          onsubmit="return confirm('Tandai pesanan ini sebagai LUNAS? Pastikan uang sudah benar-benar diterima.');">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="paid">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-green-700">
                            &#10003; Konfirmasi Pembayaran Diterima
                        </button>
                    </form>
                @endif
            </div>

            <!-- Form Ubah Status Transaksi (harus berurutan; pembatalan wajib beralasan) -->
            @if($order->nextStatus() || $order->canBeCancelled())
                <form action="{{ route('admin.orders.update', $order->id) }}" method="POST"
                      x-data="{ status: '{{ old('status', $order->nextStatus() ?? $order->status) }}' }"
                      class="bg-gray-50 p-4 rounded-md space-y-3">
                    @csrf
                    @method('PUT')
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <label class="block font-bold text-gray-700">Ubah Status Pesanan:</label>
                            <select name="status" x-model="status" class="border-gray-300 rounded-md shadow-sm mt-1">
                                @foreach(\App\Models\Order::STATUS_LABELS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}{{ $order->status === $value ? ' (saat ini)' : '' }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                Status harus berurutan: Menunggu Pembayaran &rarr; Dibayar &rarr; Diproses &rarr; Siap Diambil / Dikirim &rarr; Selesai.
                                @if($order->nextStatus())
                                    Langkah berikutnya: <strong>{{ \App\Models\Order::STATUS_LABELS[$order->nextStatus()] }}</strong>.
                                @endif
                                Status "Dibatalkan" mengembalikan stok produk dan tidak bisa dibuka kembali.
                            </p>
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-indigo-700">Update Status</button>
                    </div>

                    <div x-show="status === 'cancelled'" x-cloak>
                        <label class="block font-bold text-gray-700">Alasan Pembatalan <span class="text-red-600">*</span></label>
                        <textarea name="cancel_reason" rows="2" maxlength="255" class="w-full border-gray-300 rounded-md shadow-sm mt-1" placeholder="Wajib diisi bila membatalkan pesanan">{{ old('cancel_reason') }}</textarea>
                    </div>
                </form>
            @else
                <div class="bg-gray-50 p-4 rounded-md text-sm text-gray-600">
                    Pesanan berstatus <strong>{{ \App\Models\Order::STATUS_LABELS[$order->status] }}</strong> dan tidak bisa diubah lagi.
                </div>
            @endif

            @if($order->statusLogs->isNotEmpty())
                <div>
                    <h3 class="font-bold text-gray-700 mb-2">Riwayat Status</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        @foreach($order->statusLogs->sortByDesc('id') as $log)
                            <li>
                                {{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d/m/Y H:i') }}
                                &mdash; {{ \App\Models\Order::STATUS_LABELS[$log->status] ?? $log->status }}
                                @if($log->reason)
                                    <span class="text-gray-500">(alasan: {{ $log->reason }})</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex justify-between items-center pt-4">
                <a href="{{ route('admin.orders.index') }}" class="text-gray-600 font-semibold hover:underline">&larr; Kembali ke Daftar Order</a>
                <p class="text-xl font-extrabold text-amber-700">Total: Rp {{ number_format($order->total, 0, ',', '.') }}</p>
            </div>

        </div>
    </div>
</x-app-layout>
