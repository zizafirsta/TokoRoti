<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Keranjang Belanja Anda') }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8" x-data="cartPage(@js($items))"
         @keydown.escape.window="removing = null">

        <x-flash />

        @foreach($notices ?? [] as $notice)
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-6">
                {{ $notice }}
            </div>
        @endforeach

        {{-- Keranjang berisi --}}
        <div x-show="items.length > 0" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-4">
                <template x-for="item in items" :key="item.id">
                    <div class="bg-white rounded-lg shadow-md p-4 flex gap-4" :class="{ 'opacity-70': item.busy }">
                        <a :href="item.show_url" class="shrink-0">
                            <template x-if="item.image_url">
                                <img :src="item.image_url" :alt="item.name" class="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded-lg">
                            </template>
                            <template x-if="!item.image_url">
                                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-lg bg-amber-50 flex items-center justify-center text-3xl">🍞</div>
                            </template>
                        </a>

                        <div class="flex-1 min-w-0 flex flex-col justify-between gap-3">
                            <div class="flex justify-between gap-3">
                                <div class="min-w-0">
                                    <a :href="item.show_url" class="font-bold text-gray-900 hover:text-amber-700 block truncate" x-text="item.name"></a>
                                    <p class="text-sm text-gray-500" x-text="rupiah(item.price) + ' / pcs'"></p>
                                </div>
                                <button type="button" @click="askRemove(item)"
                                        class="shrink-0 self-start rounded-full p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                        aria-label="Hapus dari keranjang" title="Hapus">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center overflow-hidden rounded-lg border border-gray-300">
                                    <button type="button" @click="dec(item)" :disabled="item.quantity <= 1"
                                            class="h-9 w-9 text-lg font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent"
                                            aria-label="Kurangi">&minus;</button>
                                    <input type="number" min="1" :max="item.stock" :value="item.quantity"
                                           @change="setQty(item, $event.target.value); $event.target.value = item.quantity"
                                           class="h-9 w-12 border-0 border-x border-gray-300 p-0 text-center font-semibold focus:ring-0"
                                           aria-label="Jumlah">
                                    <button type="button" @click="inc(item)" :disabled="item.quantity >= item.stock"
                                            class="h-9 w-9 text-lg font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent"
                                            aria-label="Tambah">+</button>
                                </div>

                                <p class="font-extrabold text-amber-700" x-text="rupiah(item.price * item.quantity)"></p>
                            </div>

                            <p x-show="item.quantity >= item.stock" class="text-xs text-yellow-700">Jumlah maksimal sesuai stok tersedia.</p>
                        </div>
                    </div>
                </template>

                <a href="{{ route('shop.index') }}" class="inline-block text-amber-600 font-semibold hover:underline">&larr; Lanjut Belanja</a>
            </div>

            {{-- Ringkasan --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 lg:sticky lg:top-6 space-y-4">
                    <h3 class="font-bold text-lg text-gray-800">Ringkasan Belanja</h3>

                    <div class="flex justify-between text-gray-600">
                        <span>Jumlah barang</span>
                        <span class="font-semibold" x-text="count + ' pcs'"></span>
                    </div>

                    <div class="flex justify-between items-baseline border-t pt-4">
                        <span class="font-semibold text-gray-700">Total</span>
                        <span class="text-2xl font-extrabold text-amber-700" x-text="rupiah(total)"></span>
                    </div>
                    <p class="text-xs text-gray-500">Ongkos kirim dihitung di halaman checkout.</p>

                    <a href="{{ route('checkout.index') }}"
                       class="block w-full bg-green-600 text-white text-center py-3 rounded-md font-bold hover:bg-green-700 transition shadow">
                        Lanjut ke Checkout &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Keranjang kosong --}}
        <div x-show="items.length === 0" x-cloak class="bg-white rounded-lg shadow-md text-center py-16 px-4">
            <div class="text-5xl mb-3">🛒</div>
            <p class="text-gray-500 text-lg">Keranjang belanja Anda masih kosong.</p>
            <a href="{{ route('shop.index') }}" class="mt-4 inline-block bg-amber-600 text-white px-6 py-2 rounded-md font-semibold hover:bg-amber-700 transition">
                Mulai Belanja
            </a>
        </div>

        {{-- Dialog konfirmasi hapus --}}
        <div x-show="removing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/50" @click="removing = null"></div>

            <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-2xl text-red-600">!</div>
                <h3 class="text-lg font-bold text-gray-900">Hapus dari keranjang?</h3>
                <p class="mt-1 text-sm text-gray-600">
                    <span class="font-semibold" x-text="removing ? removing.name : ''"></span> akan dihapus dari keranjang Anda.
                </p>
                <div class="mt-5 flex gap-3">
                    <button type="button" @click="removing = null"
                            class="flex-1 rounded-lg border border-gray-300 py-2.5 font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="button" @click="confirmRemove()" :disabled="removeBusy"
                            class="flex-1 rounded-lg bg-red-600 py-2.5 font-bold text-white hover:bg-red-700 disabled:opacity-60">
                        <span x-text="removeBusy ? 'Menghapus...' : 'Ya, Hapus'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
