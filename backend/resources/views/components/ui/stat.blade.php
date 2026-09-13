@props(['label', 'value', 'tone' => 'neutral', 'hint' => null])
@php
    $valueTones = [
        'accent' => 'text-savv-orange',
        'info' => 'text-savv-blue',
        'success' => 'text-green-600',
        'danger' => 'text-savv-error',
        'neutral' => 'text-savv-darkgray',
    ];
@endphp

<div class="rounded-2xl border border-savv-graylight bg-white p-4">
    <div class="text-[11px] font-bold uppercase tracking-wider text-savv-gray">{{ $label }}</div>
    <div class="mt-2 text-2xl font-bold leading-none {{ $valueTones[$tone] ?? $valueTones['neutral'] }}">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1.5 text-xs text-savv-gray">{{ $hint }}</div>
    @endif
</div>
