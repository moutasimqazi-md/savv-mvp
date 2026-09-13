@props(['provider', 'size' => 'md'])
@php
    $isSubscription = $provider->kind()->value === 'subscription';
    $icon = $isSubscription ? 'credit-card.png' : 'package.png';
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-full bg-savv-light px-2.5 py-1 text-xs font-semibold text-savv-darkgray ring-1 ring-inset ring-savv-graylight',
]) }}>
    <img src="{{ asset('images/design/' . $icon) }}" alt="" class="h-3.5 w-3.5">
    {{ $provider->label() }}
</span>
