<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Formulir Pengiriman & Checkout') }}
        </h2>
    </x-slot>

    @php
        $checkoutState = [
            'type'     => old('fulfillment_type', 'delivery'),
            'method'   => old('payment_method', array_key_first($methods)),
            'subtotal' => $total,
            'shipping' => $shippingFee,
        ];
    @endphp

    <div class="py-12 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-md p-6">

            <x-flash />

            <form action="{{ route('checkout.process') }}" method="POST" class="space-y-8"
                  x-data="checkoutForm(@js($checkoutState))"
                  @submit="submit($event)">
                @csrf

                <!-- 1. Metode Pengambilan / Pengiriman -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-2">1. Metode Pengambilan / Pengiriman</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="border p-4 rounded-lg flex items-center space-x-3 cursor-pointer transition"
                               :class="type === 'delivery' ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-200' : 'hover:bg-gray-50'">
                            <input type="radio" name="fulfillment_type" value="delivery" x-model="type" class="text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="font-bold block text-gray-800">Dikirim (Delivery)</span>
                                <span class="text-xs text-gray-500">Ongkir flat Rp {{ number_format($shippingFee, 0, ',', '.') }}</span>
                            </div>
                        </label>
                        <label class="border p-4 rounded-lg flex items-center space-x-3 cursor-pointer transition"
                               :class="type === 'pickup' ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-200' : 'hover:bg-gray-50'">
                            <input type="radio" name="fulfillment_type" value="pickup" x-model="type" class="text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="font-bold block text-gray-800">Ambil Sendiri (Pickup)</span>
                                <span class="text-xs text-gray-500">Tanpa biaya pengiriman</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Alamat (hanya untuk delivery) -->
                <div x-show="type === 'delivery'" x-transition>
                    <label for="address" class="block font-semibold text-gray-700 mb-1">Alamat Pengiriman</label>
                    <textarea name="address" id="address" rows="3" :required="type === 'delivery'"
                              placeholder="Masukkan alamat lengkap pengiriman..."
                              class="w-full border-gray-300 rounded-md shadow-sm focus:border-amber-500 focus:ring-amber-500">{{ old('address') }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">*Pastikan alamat sudah lengkap yaa (jalan, nomor, patokan).</p>
                </div>

                <!-- Catatan -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Catatan Pesanan (Opsional)</label>
                    <textarea name="note" rows="2" placeholder="Contoh: Titip di satpam / tanpa lilin ulang tahun"
                              class="w-full border-gray-300 rounded-md shadow-sm focus:border-amber-500 focus:ring-amber-500">{{ old('note') }}</textarea>
                </div>

                <!-- 2. Metode Pembayaran -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-2">2. Metode Pembayaran</label>
                    <div class="grid grid-cols-1 gap-3">
                        @foreach($methods as $key => $info)
                            <label class="border p-4 rounded-lg flex items-start space-x-3 cursor-pointer transition"
                                   :class="method === '{{ $key }}' ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-200' : 'hover:bg-gray-50'">
                                <input type="radio" name="payment_method" value="{{ $key }}" x-model="method" class="mt-1 text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="font-bold block text-gray-800">{{ $info['label'] }}</span>
                                    <span class="text-xs text-gray-500">{{ $info['hint'] }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Ringkasan Pesanan -->
                <div class="border-t pt-4">
                    <h3 class="font-bold text-lg mb-2">Ringkasan Pesanan</h3>
                    <ul class="divide-y">
                        @foreach($items as $item)
                            <li class="py-2 flex justify-between gap-4">
                                <span>{{ $item['name'] }} <span class="text-gray-500">&times; {{ $item['quantity'] }}</span></span>
                                <span class="font-semibold">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-3 space-y-1 text-gray-600">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span x-text="rupiah(subtotal)">Rp {{ number_format($total, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between" x-show="shippingFee > 0">
                            <span>Ongkos Kirim</span>
                            <span x-text="rupiah(shippingFee)"></span>
                        </div>
                    </div>

                    <div class="flex justify-between font-extrabold text-xl mt-3 pt-3 border-t text-amber-700">
                        <span>Total Bayar:</span>
                        <span x-text="rupiah(total)">Rp {{ number_format($total, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3">
                    <a href="{{ route('cart.index') }}"
                       class="sm:w-1/3 text-center border border-gray-300 text-gray-700 py-3 rounded-md font-semibold hover:bg-gray-50">
                        &larr; Kembali ke Keranjang
                    </a>
                    <button type="submit" :disabled="submitting"
                            class="flex-1 bg-green-600 text-white py-3 rounded-md font-bold text-lg hover:bg-green-700 transition shadow disabled:opacity-60 disabled:cursor-not-allowed">
                        <span x-text="submitting ? 'Memproses pesanan...' : 'Buat Pesanan & Lanjut Bayar →'">Buat Pesanan &amp; Lanjut Bayar &rarr;</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
