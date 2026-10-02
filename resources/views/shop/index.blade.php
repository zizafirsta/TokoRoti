<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Katalog Roti & Kue Lezat') }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Filter Kategori -->
        <div class="flex items-center space-x-2 mb-8 overflow-x-auto pb-2">
            <a href="{{ route('shop.index') }}"
               class="px-4 py-2 rounded-full border text-sm font-semibold {{ !request('category') ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
               Semua
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('shop.index', ['category' => $cat->id]) }}"
                   class="px-4 py-2 rounded-full border text-sm font-semibold {{ request('category') == $cat->id ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                   {{ $cat->name }}
                </a>
            @endforeach
        </div>

        <x-flash />

        <!-- Grid Produk -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @forelse($products as $product)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
                    <a href="{{ route('shop.show', $product->id) }}">
                        @if($product->image_url)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-48 object-cover">
                        @else
                            <div class="w-full h-48 bg-gray-200 flex items-center justify-center text-gray-400">Tanpa Gambar</div>
                        @endif
                    </a>
                    <div class="p-4">
                        <span class="text-xs text-amber-600 font-semibold uppercase">{{ $product->category->name }}</span>
                        <h3 class="font-bold text-lg text-gray-800 mt-1">
                            <a href="{{ route('shop.show', $product->id) }}">{{ $product->name }}</a>
                        </h3>
                        <p class="text-amber-700 font-extrabold text-md mt-2">Rp {{ number_format($product->price, 0, ',', '.') }}</p>

                        @if($product->stock > 0)
                            <x-add-to-cart-button :product="$product" class="mt-4 block w-full bg-amber-600 text-white py-2 rounded-md font-semibold text-center hover:bg-amber-700 transition">
                                + Keranjang
                            </x-add-to-cart-button>
                        @else
                            <button type="button" disabled class="mt-4 w-full bg-gray-300 text-gray-500 py-2 rounded-md font-semibold cursor-not-allowed">
                                Stok Habis
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12 text-gray-500">
                    Belum ada produk roti pada kategori ini.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
