<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Roti') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <x-flash />

                <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block font-semibold">Nama Roti</label>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" required class="w-full border-gray-300 rounded-md shadow-sm">
                    </div>

                    <div>
                        <label class="block font-semibold">Kategori</label>
                        <select name="category_id" required class="w-full border-gray-300 rounded-md shadow-sm">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold">Harga (Rp)</label>
                            <input type="number" name="price" value="{{ old('price', (int) $product->price) }}" min="0" required class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block font-semibold">Stok</label>
                            <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" min="0" step="1" required class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold">Deskripsi</label>
                        <textarea name="description" rows="3" class="w-full border-gray-300 rounded-md shadow-sm">{{ old('description', $product->description) }}</textarea>
                    </div>

                    <div>
                        <label class="block font-semibold">Foto Roti (Kosongkan jika tidak diganti)</label>
                        @if($product->image_url)
                            <img src="{{ $product->image_url }}" class="w-20 h-20 object-cover my-2 rounded">
                        @endif
                        <input type="file" name="image" accept="image/*" class="w-full">
                    </div>

                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }} class="rounded border-gray-300">
                        <span class="font-semibold">Tampilkan di katalog</span>
                    </label>

                    <div class="flex justify-end space-x-2 pt-4">
                        <a href="{{ route('admin.products.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-md">Batal</a>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold">Update Produk</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
