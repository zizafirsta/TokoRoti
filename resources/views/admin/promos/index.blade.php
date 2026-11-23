<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Kelola Promo') }}</h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold">Daftar Promo</h3>
                <a href="{{ route('admin.promos.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-indigo-700">+ Tambah Promo</a>
            </div>

            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="p-3">Kode</th>
                        <th class="p-3">Diskon</th>
                        <th class="p-3">Min. Belanja</th>
                        <th class="p-3">Masa Berlaku</th>
                        <th class="p-3">Kuota</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promos as $promo)
                        <tr class="border-b">
                            <td class="p-3 font-bold">{{ $promo->code }}</td>
                            <td class="p-3">
                                {{ $promo->type === 'percent' ? rtrim(rtrim(number_format($promo->value, 2, ',', '.'), '0'), ',') . '%' : 'Rp ' . number_format($promo->value, 0, ',', '.') }}
                            </td>
                            <td class="p-3">Rp {{ number_format($promo->min_purchase, 0, ',', '.') }}</td>
                            <td class="p-3 text-sm">{{ $promo->start_date->format('d/m/Y') }} &ndash; {{ $promo->end_date->format('d/m/Y') }}</td>
                            <td class="p-3">{{ $promo->quota }}</td>
                            <td class="p-3">
                                @if($promo->is_active)
                                    <span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded-full">Aktif</span>
                                @else
                                    <span class="bg-gray-200 text-gray-600 text-xs font-bold px-2 py-1 rounded-full">Nonaktif</span>
                                @endif
                            </td>
                            <td class="p-3 text-center space-x-2">
                                <a href="{{ route('admin.promos.edit', $promo->id) }}" class="text-blue-600 hover:underline">Edit</a>
                                <form action="{{ route('admin.promos.toggle', $promo->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="{{ $promo->is_active ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                        {{ $promo->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4 text-center text-gray-500">Belum ada promo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
