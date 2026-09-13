@props(['padding' => 'p-5', 'hover' => false])

<div {{ $attributes->merge([
    'class' => 'rounded-2xl border border-savv-graylight bg-white ' . $padding . ($hover ? ' transition hover:border-savv-gray/40 hover:shadow-sm' : ''),
]) }}>
    {{ $slot }}
</div>
