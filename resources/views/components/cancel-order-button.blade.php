@props(['order'])

{{-- Tombol "Batalkan Pesanan" dengan dialog konfirmasi --}}
<div x-data="{ open: false, busy: false }" @keydown.escape.window="open = false" {{ $attributes }}>
    <button type="button" @click="open = true" class="text-sm font-semibold text-red-600 hover:underline">
        Batalkan Pesanan
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="open = false"></div>

        <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-2xl text-red-600">!</div>
            <h3 class="text-lg font-bold text-gray-900">Batalkan pesanan ini?</h3>
            <p class="mt-1 text-sm text-gray-600">
                Pesanan <span class="font-semibold">{{ $order->invoice_no }}</span> akan dibatalkan dan tidak bisa dibuka kembali.
            </p>

            <form method="POST" action="{{ route('orders.cancel', $order->id) }}" @submit="busy = true" class="mt-5 flex gap-3">
                @csrf
                <button type="button" @click="open = false" class="flex-1 rounded-lg border border-gray-300 py-2.5 font-semibold text-gray-700 hover:bg-gray-50">
                    Kembali
                </button>
                <button type="submit" :disabled="busy" class="flex-1 rounded-lg bg-red-600 py-2.5 font-bold text-white hover:bg-red-700 disabled:opacity-60">
                    <span x-text="busy ? 'Memproses...' : 'Ya, Batalkan'"></span>
                </button>
            </form>
        </div>
    </div>
</div>
