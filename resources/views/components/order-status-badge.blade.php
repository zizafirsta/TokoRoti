@props(['status'])

@php
    $classes = [
        'pending'    => 'bg-yellow-100 text-yellow-800',
        'paid'       => 'bg-green-100 text-green-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'ready'      => 'bg-indigo-100 text-indigo-800',
        'shipped'    => 'bg-indigo-100 text-indigo-800',
        'completed'  => 'bg-green-100 text-green-800',
        'cancelled'  => 'bg-red-100 text-red-800',
    ][$status] ?? 'bg-gray-100 text-gray-800';

    $label = \App\Models\Order::STATUS_LABELS[$status] ?? ucfirst($status);
@endphp

<span {{ $attributes->merge(['class' => 'inline-block text-xs font-bold px-3 py-1 rounded-full ' . $classes]) }}>
    {{ strtoupper($label) }}
</span>
