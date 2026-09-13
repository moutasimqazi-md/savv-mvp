@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])
@php
    $sizes = [
        'sm' => 'px-3.5 py-1.5 text-xs',
        'md' => 'px-5 py-2.5 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];

    $variants = [
        'primary' => 'bg-black text-white hover:bg-savv-darkgray focus-visible:ring-black',
        'accent' => 'bg-savv-orange text-white shadow-sm shadow-savv-orange/30 hover:bg-orange-600 focus-visible:ring-savv-orange',
        'outline' => 'border border-savv-graylight bg-white text-savv-darkgray hover:border-savv-gray hover:bg-savv-light focus-visible:ring-savv-gray',
        'ghost' => 'text-savv-darkgray hover:bg-savv-graylight focus-visible:ring-savv-gray',
        'danger' => 'border border-savv-error/30 bg-white text-savv-error hover:bg-savv-error/5 focus-visible:ring-savv-error',
        'danger-solid' => 'bg-savv-error text-white hover:bg-red-700 focus-visible:ring-savv-error',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-40',
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['primary'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
