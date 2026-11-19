@props(['payment'])

@php
    if (! $payment) {
        $label = 'Menunggu Pembayaran';
        $classes = 'bg-yellow-100 text-yellow-800';
    } elseif ($payment->status === 'settlement') {
        $label = 'Pembayaran Diterima';
        $classes = 'bg-green-100 text-green-800';
    } elseif (in_array($payment->status, ['cancel', 'expire', 'deny'], true)) {
        $label = 'Dibatalkan';
        $classes = 'bg-red-100 text-red-800';
    } elseif ($payment->isAwaitingVerification()) {
        $label = 'Menunggu Verifikasi Admin';
        $classes = 'bg-blue-100 text-blue-800';
    } elseif ($payment->method === 'cash') {
        $label = 'Bayar di Tempat';
        $classes = 'bg-gray-100 text-gray-700';
    } else {
        $label = 'Menunggu Pembayaran';
        $classes = 'bg-yellow-100 text-yellow-800';
    }
@endphp

<span {{ $attributes->merge(['class' => 'inline-block text-xs font-bold px-3 py-1 rounded-full ' . $classes]) }}>
    {{ $label }}
</span>
