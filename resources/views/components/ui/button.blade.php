@props(['variant' => 'primary'])

@php
    $classes = match ($variant) {
        'primary' => 'w-full py-2.5 rounded-lg font-semibold text-sm text-white bg-primary active:bg-secondary transition',
        'secondary' => 'w-full py-2.5 rounded-lg font-semibold text-sm text-gray-700 bg-gray-100 active:bg-gray-200 transition',
        'danger' => 'w-full py-2.5 rounded-lg font-semibold text-sm text-white bg-red-600 active:bg-red-700 transition',
    };
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
    {{ $slot }}
</button>
