{{-- Notifikasi singkat (toast). Dipakai lewat Alpine.store('toast').show(pesan, tipe) --}}
<div x-data class="fixed top-4 right-4 z-[60] flex flex-col gap-2 w-[calc(100%-2rem)] sm:w-96 pointer-events-none" aria-live="polite">
    <template x-for="t in $store.toast.items" :key="t.id">
        <div class="toast-in pointer-events-auto flex items-start gap-3 rounded-lg border bg-white px-4 py-3 shadow-lg"
             :class="{
                'border-green-300': t.type === 'success',
                'border-yellow-300': t.type === 'warning',
                'border-red-300': t.type === 'error'
             }">
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                  :class="{
                    'bg-green-500': t.type === 'success',
                    'bg-yellow-500': t.type === 'warning',
                    'bg-red-500': t.type === 'error'
                  }"
                  x-text="t.type === 'success' ? '✓' : '!'"></span>
            <p class="flex-1 text-sm text-gray-700" x-text="t.message"></p>
            <button type="button" @click="$store.toast.dismiss(t.id)" class="text-gray-400 hover:text-gray-600" aria-label="Tutup">&times;</button>
        </div>
    </template>
</div>
