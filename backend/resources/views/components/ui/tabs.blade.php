@props(['tabs' => [], 'current' => ''])
{{--
    Segmented pill navigation, per the design system's "navigation island":
    a rounded track with a filled dark pill marking the active segment.
    Each tab: ['label' => string, 'value' => string, 'url' => string, 'count' => ?int]
--}}

<div class="-mx-1 overflow-x-auto px-1 pb-1">
    <div class="inline-flex items-center gap-1 rounded-full border border-savv-graylight bg-white p-1">
        @foreach ($tabs as $tab)
            @php $active = (string) $current === (string) $tab['value']; @endphp
            <a href="{{ $tab['url'] }}"
               @class([
                   'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-4 py-1.5 text-[11px] font-bold uppercase tracking-wider transition',
                   'bg-savv-darkgray text-white' => $active,
                   'text-savv-gray hover:bg-savv-light hover:text-savv-darkgray' => ! $active,
               ])>
                {{ $tab['label'] }}
                @if (! empty($tab['count']))
                    <span @class([
                        'rounded-full px-1.5 py-0.5 text-[10px] leading-none',
                        'bg-white/20 text-white' => $active,
                        'bg-savv-graylight text-savv-gray' => ! $active,
                    ])>{{ $tab['count'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
