@props(['tone' => 'neutral', 'dot' => false])
@php
    $tones = [
        'success' => 'bg-savv-green/10 text-green-700 ring-savv-green/25',
        'info' => 'bg-savv-blue/10 text-savv-blue ring-savv-blue/25',
        'warning' => 'bg-savv-orange/10 text-orange-700 ring-savv-orange/25',
        'danger' => 'bg-savv-error/10 text-savv-error ring-savv-error/25',
        'neutral' => 'bg-savv-graylight text-savv-darkgray ring-savv-gray/20',
    ];

    $dotTones = [
        'success' => 'bg-savv-green',
        'info' => 'bg-savv-blue',
        'warning' => 'bg-savv-orange',
        'danger' => 'bg-savv-error',
        'neutral' => 'bg-savv-gray',
    ];

    $tone = isset($tones[$tone]) ? $tone : 'neutral';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ' . $tones[$tone]]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dotTones[$tone] }}"></span>
    @endif
    {{ $slot }}
</span>
