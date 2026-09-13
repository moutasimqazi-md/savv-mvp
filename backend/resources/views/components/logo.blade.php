@props(['size' => 'text-2xl'])
<span {{ $attributes->merge(['class' => "inline-flex select-none items-center font-black italic tracking-tighter {$size}"]) }}>
    <span class="bg-gradient-to-br from-savv-orange to-orange-600 bg-clip-text text-transparent">SA</span><span class="text-current">VV</span>
</span>
