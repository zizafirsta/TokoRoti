<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Kelola Stok Produk') }}</h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <x-flash />

        <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="p-3">Produk</th>
                        <th class="p-3">Stok</th>
                        <th class="p-3">Min.</th>
                        <th class="p-3">Penyesuaian Stok</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr class="border-b align-top">
                            <td class="p-3 font-semibold">
                                {{ $product->name }}
                                <span class="block text-xs text-gray-500 font-normal">{{ $product->category->name ?? '-' }}</span>
                            </td>
                            <td class="p-3">
                                <span class="font-bold">{{ $product->stock }}</span>
                                @if($product->isLowStock())
                                    <span class="ml-1 bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-0.5 rounded-full">Menipis</span>
                                @endif
                            </td>
                            <td class="p-3 text-gray-500">{{ $product->min_stock }}</td>
                            <td class="p-3">
                                <form action="{{ route('admin.stock.adjust', $product->id) }}" method="POST" class="flex flex-wrap gap-2 items-center">
                                    @csrf
                                    <select name="type" class="border-gray-300 rounded-md text-sm">
                                        <option value="add">Tambah</option>
                                        <option value="reduce">Kurangi</option>
                                    </select>
                                    <input type="number" name="quantity" min="1" step="1" placeholder="Jumlah" required class="w-24 border-gray-300 rounded-md text-sm">
                                    <input type="text" name="reason" placeholder="Alasan (wajib)" required maxlength="255" class="w-56 border-gray-300 rounded-md text-sm">
                                    <button type="submit" class="bg-indigo-600 text-white text-sm px-3 py-1.5 rounded-md font-semibold hover:bg-indigo-700">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4 text-center text-gray-500">Belum ada produk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
            <h3 class="font-bold text-gray-700 mb-3">Riwayat Penyesuaian Stok Terbaru</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="p-2">Waktu</th>
                        <th class="p-2">Produk</th>
                        <th class="p-2">Perubahan</th>
                        <th class="p-2">Stok</th>
                        <th class="p-2">Alasan</th>
                        <th class="p-2">Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $row)
                        <tr class="border-b">
                            <td class="p-2 text-gray-500">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                            <td class="p-2">{{ $row->product?->name ?? '-' }}</td>
                            <td class="p-2 font-semibold {{ $row->type === 'add' ? 'text-green-700' : 'text-red-700' }}">
                                {{ $row->type === 'add' ? '+' : '-' }}{{ $row->quantity }}
                            </td>
                            <td class="p-2">{{ $row->stock_before }} &rarr; {{ $row->stock_after }}</td>
                            <td class="p-2">{{ $row->reason }}</td>
                            <td class="p-2 text-gray-500">{{ $row->user?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-3 text-center text-gray-500">Belum ada riwayat penyesuaian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
