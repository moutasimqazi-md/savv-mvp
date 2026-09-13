@props(['title', 'description' => null, 'image' => 'illustration-tracking.png'])

<div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
    <img src="{{ asset('images/design/' . $image) }}" alt="" class="h-16 w-16">
    <div class="text-sm font-bold text-savv-darkgray">{{ $title }}</div>
    @if ($description)
        <p class="max-w-sm text-sm text-savv-gray">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
