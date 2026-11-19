<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-amber-950 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <!-- NOTIFIKASI USER LOGGED IN -->
    <div class="pt-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="bg-amber-100/70 border border-amber-200 overflow-hidden shadow-sm rounded-xl">
            <div class="p-4 text-amber-950 font-medium flex items-center space-x-2">
                <span>👋</span>
                <span>{{ __("You're logged in!") }} Selamat datang di ZadaraBakery.</span>
            </div>
        </div>
    </div>

    <!-- HERO & BANNER SHOWCASE ROTI -->
    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-amber-950 text-amber-50 relative overflow-hidden rounded-3xl p-8 sm:p-12 shadow-2xl border border-amber-900">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">

                <!-- Deskripsi & Brand -->
                <div class="space-y-4 z-10">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-amber-600 rounded-full flex items-center justify-center text-white font-bold text-2xl shadow-md border-2 border-amber-300">
                            🍞
                        </div>
                        <span class="text-amber-300 tracking-widest text-xs sm:text-sm font-semibold uppercase">ZadaraBakery Signature</span>
                    </div>
                    <h1 class="text-3xl sm:text-5xl font-extrabold text-amber-100 leading-tight">
                        Kehangatan Roti Segar Langsung Dari Oven
                    </h1>
                    <p class="text-amber-200/80 text-sm sm:text-base">
                        Dibuat dengan bahan premium pilihan setiap hari. Rasakan kelembutan dan kenikmatan tekstur roti khas racikan kami.
                    </p>
                    <div class="pt-2">
                        <a href="#katalog" class="inline-block bg-amber-600 hover:bg-amber-500 text-amber-950 font-bold px-6 py-3 rounded-full shadow-lg hover:shadow-amber-600/50 transition transform hover:-translate-y-0.5">
                            Jelajahi Menu Roti ↓
                        </a>
                    </div>
                </div>

                <!-- SHOWCASE ROTI UNGGULAN (roti1.png, roti2.png, roti3.png) -->
                <div class="grid grid-cols-3 gap-3 relative z-10">
                    <div class="group relative rounded-2xl overflow-hidden shadow-lg border-2 border-amber-800/60 hover:border-amber-400 transition">
                        <img src="{{ asset('images/roti1.png') }}" alt="Roti 1" class="w-full h-36 sm:h-48 object-cover group-hover:scale-110 transition duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-2">
                            <span class="text-xs font-medium text-amber-200">Croissant Crispy</span>
                        </div>
                    </div>
                    <div class="group relative rounded-2xl overflow-hidden shadow-lg border-2 border-amber-800/60 hover:border-amber-400 transition transform sm:-translate-y-3">
                        <img src="{{ asset('images/roti2.png') }}" alt="Roti 2" class="w-full h-36 sm:h-48 object-cover group-hover:scale-110 transition duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-2">
                            <span class="text-xs font-medium text-amber-200">Soft Bun Chocolate</span>
                        </div>
                    </div>
                    <div class="group relative rounded-2xl overflow-hidden shadow-lg border-2 border-amber-800/60 hover:border-amber-400 transition">
                        <img src="{{ asset('images/roti3.png') }}" alt="Roti 3" class="w-full h-36 sm:h-48 object-cover group-hover:scale-110 transition duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-2">
                            <span class="text-xs font-medium text-amber-200">Artisan Sourdough</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- KATALOG PRODUK ROTI -->
    <div id="katalog" class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8">
            <h3 class="text-2xl sm:text-3xl font-bold text-amber-950">Menu Roti Pilihan Hari Ini</h3>
            <div class="w-16 h-1 bg-amber-600 mx-auto mt-2 rounded-full"></div>
        </div>

        <!-- Grid Card Roti -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @if(isset($products) && count($products) > 0)
                @foreach($products as $product)
                    <div class="bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition duration-300 border border-amber-100 flex flex-col justify-between group">
                        <div>
                            <!-- Foto Produk -->
                            <div class="relative overflow-hidden h-48 bg-amber-100">
                                @if($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                @else
                                    <img src="{{ asset('images/roti1.png') }}" alt="Default Roti" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                @endif
                                <span class="absolute top-3 right-3 bg-amber-950/80 text-amber-100 text-xs px-2.5 py-1 rounded-full backdrop-blur-sm">
                                    Stok: {{ $product->stock }}
                                </span>
                            </div>

                            <!-- Informasi Roti -->
                            <div class="p-5">
                                <h4 class="font-bold text-lg text-amber-950 group-hover:text-amber-700 transition">
                                    {{ $product->name }}
                                </h4>
                                <p class="text-gray-600 text-xs mt-1 line-clamp-2">
                                    {{ $product->description ?? 'Roti lezat bertekstur lembut dengan bahan baku kualitas premium.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Harga & Tombol Keranjang -->
                        <div class="px-5 pb-5 pt-2 flex items-center justify-between border-t border-amber-50">
                            <div>
                                <span class="text-xs text-amber-800/70 block">Harga</span>
                                <span class="text-lg font-extrabold text-amber-900">
                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                </span>
                            </div>

                            @if($product->stock > 0)
                                <x-add-to-cart-button :product="$product" class="bg-amber-800 hover:bg-amber-900 text-white p-2.5 rounded-xl shadow-md transition flex items-center space-x-1" aria-label="Tambah ke keranjang">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path>
                                        </svg>
                                </x-add-to-cart-button>
                            @else
                                <span class="text-xs font-semibold text-gray-400">Stok Habis</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <!-- Tampilan Default jika belum ada data produk dari DB -->
                <div class="col-span-full bg-white rounded-2xl p-8 text-center border border-amber-100 shadow-sm">
                    <p class="text-amber-900 font-semibold">Belum ada produk roti di katalog.</p>
                    <p class="text-gray-500 text-sm mt-1">Tambahkan produk baru melalui menu Admin (Kelola Produk).</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
