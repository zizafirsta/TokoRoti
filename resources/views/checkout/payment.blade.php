<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Pembayaran Pesanan') }}
        </h2>
    </x-slot>

    @php
        $payment = $order->payment;
        $method = $payment?->method;
        $totalFormatted = 'Rp ' . number_format($order->total, 0, ',', '.');
        $rawTotal = (string) (int) $order->total;

        $waLink = null;
        if ($whatsapp) {
            $waText = "Halo, saya sudah melakukan pembayaran untuk pesanan {$order->invoice_no} sebesar {$totalFormatted}"
                . ($payment ? " via {$payment->method_label}" : '') . '. Mohon dicek ya. Terima kasih.';
            $waLink = 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($waText);
        }
    @endphp

    <div class="py-12 max-w-2xl mx-auto px-4 sm:px-6 space-y-6" x-data="paymentPage">
        <x-flash />

        <!-- Ringkasan & total -->
        <div class="bg-white rounded-lg shadow-md p-6 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-800">Pesanan Dibuat!</h3>
            <p class="text-gray-600 mt-1">No. Invoice: <span class="font-semibold">{{ $order->invoice_no }}</span></p>

            <p class="mt-4 text-sm text-gray-500">Total yang harus dibayar</p>
            <div class="flex items-center justify-center gap-3">
                <p class="text-3xl font-extrabold text-amber-700">{{ $totalFormatted }}</p>
                @if(in_array($method, ['bank_transfer', 'qris'], true))
                    <button type="button" @click="copy('{{ $rawTotal }}', 'total')"
                            class="rounded-md border border-amber-300 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50">
                        <span x-text="copied === 'total' ? 'Tersalin ✓' : 'Salin nominal'">Salin nominal</span>
                    </button>
                @endif
            </div>

            <div class="mt-3 flex justify-center">
                <x-payment-status-badge :payment="$payment" />
            </div>
        </div>

        <!-- Petunjuk sesuai metode -->
        @if($method === 'bank_transfer')
            <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
                <h3 class="font-bold text-gray-800">Transfer ke salah satu rekening berikut</h3>

                @forelse($banks as $i => $bank)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-4">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-amber-700">{{ $bank['bank'] }}</p>
                            <p class="text-xl font-extrabold tracking-wide text-gray-900">{{ $bank['number'] }}</p>
                            @if(!empty($bank['holder']))
                                <p class="text-sm text-gray-500">a.n. {{ $bank['holder'] }}</p>
                            @endif
                        </div>
                        <button type="button" @click="copy('{{ preg_replace('/\s+/', '', $bank['number']) }}', 'bank{{ $i }}')"
                                class="shrink-0 rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                            <span x-text="copied === 'bank{{ $i }}' ? 'Tersalin ✓' : 'Salin'">Salin</span>
                        </button>
                    </div>
                @empty
                    <p class="text-sm text-red-600">Rekening tujuan belum diatur oleh toko. Silakan hubungi admin.</p>
                @endforelse

                <p class="text-xs text-gray-500">Transfer sesuai nominal di atas, lalu unggah bukti transfer pada bagian bawah halaman ini.</p>
            </div>

        @elseif($method === 'qris')
            <div class="bg-white rounded-lg shadow-md p-6 text-center space-y-3">
                <h3 class="font-bold text-gray-800">Scan QRIS untuk membayar</h3>
                @if($qrisUrl)
                    <img src="{{ $qrisUrl }}" alt="QRIS {{ config('toko.name') }}" class="mx-auto max-h-96 rounded-lg border">
                    <a href="{{ $qrisUrl }}" download class="inline-block text-sm font-semibold text-amber-700 hover:underline">Unduh gambar QRIS</a>
                @else
                    <p class="text-sm text-red-600">Gambar QRIS belum tersedia. Silakan hubungi admin.</p>
                @endif
                <p class="text-xs text-gray-500">Pastikan nominal pembayaran sesuai total pesanan, lalu unggah bukti bayar di bawah.</p>
            </div>

        @elseif($method === 'cash')
            <div class="bg-white rounded-lg shadow-md p-6 space-y-2">
                <h3 class="font-bold text-gray-800">Bayar di Tempat (Tunai)</h3>
                <p class="text-gray-600">
                    Tidak perlu transfer sekarang. Siapkan uang tunai
                    <strong>{{ $totalFormatted }}</strong> dan bayar saat pesanan
                    {{ $order->fulfillment_type === 'delivery' ? 'diantar ke alamat Anda' : 'Anda ambil di toko' }}.
                </p>
                <p class="text-sm text-gray-500">Admin akan mengonfirmasi dan memproses pesanan Anda.</p>
            </div>

        @else
            <div class="bg-white rounded-lg shadow-md p-6 space-y-3">
                <h3 class="font-bold text-gray-800">Metode pembayaran tidak tersedia</h3>
                <p class="text-gray-600">Metode pembayaran untuk pesanan ini sudah tidak didukung. Silakan batalkan pesanan lalu buat pesanan baru, atau hubungi toko.</p>
            </div>
        @endif

        <!-- Unggah bukti pembayaran -->
        @if($payment && $payment->needsProof())
            <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
                <h3 class="font-bold text-gray-800">
                    {{ $payment->proof ? 'Bukti pembayaran sudah dikirim' : 'Unggah bukti pembayaran' }}
                </h3>

                @if($payment->proof)
                    <div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-800">
                        Bukti dikirim pada {{ $payment->proof_uploaded_at?->locale('id')->translatedFormat('d F Y, H:i') }}.
                        Menunggu verifikasi admin. Anda bisa mengganti bukti jika salah kirim.
                    </div>
                    <a href="{{ route('orders.proof', $order->id) }}" target="_blank" class="block">
                        <img src="{{ route('orders.proof', $order->id) }}" alt="Bukti pembayaran" class="max-h-64 rounded-lg border">
                    </a>
                @endif

                <form action="{{ route('checkout.proof', $order->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4" @submit="submit($event)">
                    @csrf

                    <div>
                        <label for="payer_name" class="block text-sm font-semibold text-gray-700 mb-1">Nama pengirim / pemilik rekening (opsional)</label>
                        <input type="text" name="payer_name" id="payer_name" maxlength="100" value="{{ old('payer_name', $payment->payer_name) }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>

                    <div>
                        <label for="proof" class="block text-sm font-semibold text-gray-700 mb-1">Foto / screenshot bukti (JPG, PNG, WEBP - maks. 4 MB)</label>
                        <input type="file" name="proof" id="proof" accept="image/png,image/jpeg,image/webp" required @change="pick($event)"
                               class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:font-semibold file:text-amber-800 hover:file:bg-amber-200">
                    </div>

                    <template x-if="preview">
                        <div>
                            <img :src="preview" alt="Pratinjau bukti" class="max-h-64 rounded-lg border">
                            <p class="mt-1 text-xs text-gray-500" x-text="fileName"></p>
                        </div>
                    </template>

                    <button type="submit" :disabled="uploading"
                            class="w-full rounded-md bg-green-600 py-3 font-bold text-white hover:bg-green-700 disabled:opacity-60">
                        <span x-text="uploading ? 'Mengunggah...' : '{{ $payment->proof ? 'Ganti Bukti Pembayaran' : 'Kirim Bukti Pembayaran' }}'"></span>
                    </button>
                </form>
            </div>
        @endif

        @if($waLink)
            <a href="{{ $waLink }}" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 rounded-lg border border-green-600 bg-white py-3 font-bold text-green-700 hover:bg-green-50">
                Konfirmasi via WhatsApp
            </a>
        @endif

        <!-- Rincian pesanan -->
        <details class="bg-white rounded-lg shadow-md p-6">
            <summary class="cursor-pointer font-bold text-gray-800">Rincian pesanan</summary>
            <div class="mt-4 text-sm text-gray-600 space-y-1">
                <p><strong>Tipe Layanan:</strong> {{ $order->fulfillment_type === 'delivery' ? 'Dikirim (Delivery)' : 'Ambil Sendiri (Pickup)' }}</p>
                @if($order->note)
                    <p><strong>Keterangan:</strong> {{ $order->note }}</p>
                @endif

                <ul class="border-t mt-2 pt-2">
                    @foreach($order->items as $item)
                        <li class="flex justify-between">
                            <span>{{ $item->product?->name ?? 'Produk dihapus' }} (x{{ $item->quantity }})</span>
                            <span>Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="border-t mt-2 pt-2"><strong>Subtotal:</strong> Rp {{ number_format($order->subtotal, 0, ',', '.') }}</p>
                @if($order->shipping_fee > 0)
                    <p><strong>Ongkos Kirim:</strong> Rp {{ number_format($order->shipping_fee, 0, ',', '.') }}</p>
                @endif
            </div>
        </details>

        <div class="flex items-center justify-between">
            <a href="{{ route('orders.show', $order->id) }}" class="font-semibold text-amber-700 hover:underline">Lihat detail pesanan &rarr;</a>
            @unless($payment?->proof)
                <x-cancel-order-button :order="$order" />
            @endunless
        </div>
    </div>
</x-app-layout>
