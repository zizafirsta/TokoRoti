<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Tambah Promo') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <x-flash />
                <form action="{{ route('admin.promos.store') }}" method="POST" class="space-y-4">
                    @csrf
                    @include('admin.promos._form')
                    <div class="flex justify-end space-x-2 pt-4">
                        <a href="{{ route('admin.promos.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-md">Batal</a>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold">Simpan Promo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
