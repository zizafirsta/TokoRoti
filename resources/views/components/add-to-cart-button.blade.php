@props(['product'])

@php
    $payload = [
        'id'     => $product->id,
        'name'   => $product->name,
        'price'  => (int) $product->price,
        'stock'  => (int) $product->stock,
        'image'  => $product->image_url,
        'addUrl' => route('cart.add', $product->id),
    ];
@endphp

@auth
    {{-- Klik membuka modal konfirmasi (pilih jumlah -> masukkan ke keranjang) --}}
    <button type="button"
            x-data
            @click="$dispatch('open-add-to-cart', @js($payload))"
            {{ $attributes }}>
        {{ $slot }}
    </button>
@else
    <a href="{{ route('login') }}" title="Login untuk mulai belanja" {{ $attributes }}>
        {{ $slot }}
    </a>
@endauth
