@php $p = $promo ?? null; @endphp
<div>
    <label class="block font-semibold">Kode Promo</label>
    <input type="text" name="code" value="{{ old('code', $p?->code) }}" required maxlength="50" class="w-full border-gray-300 rounded-md shadow-sm uppercase">
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block font-semibold">Tipe</label>
        <select name="type" required class="w-full border-gray-300 rounded-md shadow-sm">
            <option value="percent" {{ old('type', $p?->type) === 'percent' ? 'selected' : '' }}>Persen (%)</option>
            <option value="fixed" {{ old('type', $p?->type) === 'fixed' ? 'selected' : '' }}>Nominal (Rp)</option>
        </select>
    </div>
    <div>
        <label class="block font-semibold">Nilai</label>
        <input type="number" name="value" value="{{ old('value', $p ? (float) $p->value : '') }}" min="0" step="any" required class="w-full border-gray-300 rounded-md shadow-sm">
    </div>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block font-semibold">Minimal Belanja (Rp)</label>
        <input type="number" name="min_purchase" value="{{ old('min_purchase', $p ? (int) $p->min_purchase : 0) }}" min="0" class="w-full border-gray-300 rounded-md shadow-sm">
    </div>
    <div>
        <label class="block font-semibold">Kuota</label>
        <input type="number" name="quota" value="{{ old('quota', $p?->quota) }}" min="1" step="1" required class="w-full border-gray-300 rounded-md shadow-sm">
    </div>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block font-semibold">Berlaku Mulai</label>
        <input type="date" name="start_date" value="{{ old('start_date', $p?->start_date?->format('Y-m-d')) }}" required class="w-full border-gray-300 rounded-md shadow-sm">
    </div>
    <div>
        <label class="block font-semibold">Berlaku Sampai</label>
        <input type="date" name="end_date" value="{{ old('end_date', $p?->end_date?->format('Y-m-d')) }}" required class="w-full border-gray-300 rounded-md shadow-sm">
    </div>
</div>

<label class="flex items-center space-x-2">
    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $p?->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
    <span class="font-semibold">Promo aktif</span>
</label>
