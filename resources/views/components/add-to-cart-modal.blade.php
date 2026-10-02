{{-- Modal konfirmasi "Tambah ke Keranjang": pilih jumlah, konfirmasi, lalu tampilkan hasil. --}}
<div x-data="addToCartModal"
     x-cloak
     @open-add-to-cart.window="show($event.detail)"
     @keydown.escape.window="close()"
     x-effect="document.body.classList.toggle('overflow-hidden', visible)">

    <div x-show="visible" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4" role="dialog" aria-modal="true">

        <!-- Latar gelap -->
        <div x-show="visible"
             x-transition.opacity
             class="absolute inset-0 bg-black/50"
             @click="close()"></div>

        <!-- Panel -->
        <div x-show="visible"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl overflow-hidden">

            <button type="button" @click="close()"
                    class="absolute top-3 right-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-600 shadow hover:bg-white"
                    aria-label="Tutup">&times;</button>

            <!-- TAHAP 1: pilih jumlah -->
            <div x-show="stage === 'choose'">
                <template x-if="product.image">
                    <img :src="product.image" :alt="product.name" class="h-44 w-full object-cover">
                </template>
                <template x-if="!product.image">
                    <div class="flex h-44 w-full items-center justify-center bg-amber-50 text-amber-300 text-5xl">🍞</div>
                </template>

                <div class="p-5 space-y-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900" x-text="product.name"></h3>
                        <p class="mt-1 text-lg font-extrabold text-amber-700" x-text="rupiah(product.price)"></p>
                        <p class="mt-1 text-xs text-gray-500">
                            Stok tersedia: <span class="font-semibold" x-text="product.stock"></span> pcs
                            <template x-if="inCart > 0">
                                <span> &middot; sudah ada <span class="font-semibold text-amber-700" x-text="inCart"></span> di keranjang</span>
                            </template>
                        </p>
                    </div>

                    <!-- Atur jumlah -->
                    <div x-show="max > 0" class="flex items-center justify-between">
                        <span class="font-semibold text-gray-700">Jumlah</span>
                        <div class="flex items-center overflow-hidden rounded-lg border border-gray-300">
                            <button type="button" @click="dec()" :disabled="qty <= 1"
                                    class="h-10 w-10 text-xl font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent"
                                    aria-label="Kurangi">&minus;</button>
                            <input type="number" x-model.number="qty" @change="normalize()" min="1" :max="max"
                                   class="h-10 w-14 border-0 border-x border-gray-300 p-0 text-center font-bold focus:ring-0"
                                   aria-label="Jumlah">
                            <button type="button" @click="inc()" :disabled="qty >= max"
                                    class="h-10 w-10 text-xl font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent"
                                    aria-label="Tambah">+</button>
                        </div>
                    </div>

                    <p x-show="max < 1" class="rounded-lg bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                        Jumlah di keranjang sudah mencapai stok yang tersedia.
                    </p>

                    <div x-show="max > 0" class="flex items-center justify-between border-t pt-3">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="text-xl font-extrabold text-amber-700" x-text="rupiah(subtotal)"></span>
                    </div>

                    <p x-show="error" x-text="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                    <div class="flex gap-3">
                        <button type="button" @click="close()"
                                class="flex-1 rounded-lg border border-gray-300 py-3 font-semibold text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="button" @click="confirm()" :disabled="loading || max < 1"
                                class="flex flex-[2] items-center justify-center gap-2 rounded-lg bg-amber-600 py-3 font-bold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <svg x-show="loading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle>
                                <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                            </svg>
                            <span x-text="loading ? 'Menyimpan...' : 'Masukkan ke Keranjang'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAHAP 2: konfirmasi berhasil -->
            <template x-if="stage === 'success' && result">
                <div class="p-8 text-center space-y-4">
                    <div class="pop-in mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                        <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>

                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Berhasil ditambahkan!</h3>
                        <p class="mt-1 text-gray-600">
                            <span class="font-bold" x-text="result.added"></span> &times;
                            <span class="font-semibold" x-text="product.name"></span> masuk ke keranjang.
                        </p>
                        <p x-show="result.adjusted" class="mt-2 rounded-lg bg-yellow-50 px-3 py-2 text-sm text-yellow-800" x-text="result.message"></p>
                        <p class="mt-2 text-sm text-gray-500">
                            Total di keranjang: <span class="font-semibold text-gray-700" x-text="result.cart_count"></span> item
                            (<span class="font-semibold text-amber-700" x-text="rupiah(result.cart_total)"></span>)
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="button" @click="close()"
                                class="flex-1 rounded-lg border border-gray-300 py-3 font-semibold text-gray-700 hover:bg-gray-50">
                            Lanjut Belanja
                        </button>
                        <a :href="result.cart_url"
                           class="flex-1 rounded-lg bg-green-600 py-3 font-bold text-white transition hover:bg-green-700">
                            Lihat Keranjang &rarr;
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
