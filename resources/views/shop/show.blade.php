<x-app-layout>
    <div class="py-12 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="bg-white rounded-lg shadow-lg overflow-hidden grid grid-cols-1 md:grid-cols-2 gap-8 p-6">

            <!-- Gambar Produk -->
            <div>
                @if($product->image_url)
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-96 object-cover rounded-lg">
                @else
                    <div class="w-full h-96 bg-gray-200 flex items-center justify-center text-gray-400 rounded-lg">Tanpa Gambar</div>
                @endif
            </div>

            <!-- Detail Produk -->
            <div class="flex flex-col justify-between">
                <div>
                    <span class="text-sm font-semibold text-amber-600 uppercase">{{ $product->category->name }}</span>
                    <h1 class="text-3xl font-bold text-gray-900 mt-1">{{ $product->name }}</h1>
                    <p class="text-2xl font-extrabold text-amber-700 mt-3">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                    <p class="text-sm text-gray-500 mt-1">Stok Tersedia: {{ $product->stock }} pcs</p>

                    <div class="mt-6 border-t pt-4">
                        <h3 class="font-semibold text-gray-800">Deskripsi:</h3>
                        <p class="text-gray-600 mt-2 leading-relaxed">{{ $product->description ?? 'Tidak ada deskripsi produk.' }}</p>
                    </div>
                </div>

                <!-- Form Tambah ke Keranjang -->
                @if($product->stock > 0)
                    <x-add-to-cart-button :product="$product" class="mt-8 block w-full bg-amber-600 text-white py-3 rounded-md font-bold text-lg text-center hover:bg-amber-700 transition">
                        Tambahkan ke Keranjang
                    </x-add-to-cart-button>
                @else
                    <button type="button" disabled class="mt-8 w-full bg-gray-300 text-gray-500 py-3 rounded-md font-bold text-lg cursor-not-allowed">
                        Stok Habis
                    </button>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
