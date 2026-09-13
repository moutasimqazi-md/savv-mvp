@props(['steps' => [], 'current' => 0])
{{-- Delivery progress track: completed milestones fill orange with a check. --}}

<ol class="flex items-start">
    @foreach ($steps as $index => $label)
        @php
            $done = $index <= $current;
            $isCurrent = $index === $current;
        @endphp
        <li class="flex flex-1 flex-col items-center">
            <div class="flex w-full items-center">
                <div @class([
                    'h-1 flex-1 rounded-full',
                    'bg-savv-orange' => $index <= $current && ! $loop->first,
                    'bg-savv-graylight' => $index > $current && ! $loop->first,
                    'bg-transparent' => $loop->first,
                ])></div>

                <div @class([
                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full',
                    'bg-savv-orange text-white' => $done,
                    'bg-savv-graylight text-savv-gray' => ! $done,
                    'ring-4 ring-savv-orange/20' => $isCurrent,
                ])>
                    @if ($done)
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.79a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <span class="h-1.5 w-1.5 rounded-full bg-savv-gray"></span>
                    @endif
                </div>

                <div @class([
                    'h-1 flex-1 rounded-full',
                    'bg-savv-orange' => $index < $current && ! $loop->last,
                    'bg-savv-graylight' => $index >= $current && ! $loop->last,
                    'bg-transparent' => $loop->last,
                ])></div>
            </div>

            <span @class([
                'mt-2 px-1 text-center text-[11px] font-semibold leading-tight',
                'text-savv-darkgray' => $done,
                'text-savv-gray' => ! $done,
            ])>{{ $label }}</span>
        </li>
    @endforeach
</ol>
