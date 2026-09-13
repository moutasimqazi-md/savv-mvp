@php
    use Savv\Support\StatusPresenter;
@endphp

<x-layout title="Returns - Savv">

    <x-ui.page-header title="Returns" subtitle="Return requests picked up from your order history." />

    <div class="space-y-3">
        @forelse ($returns as $return)
            <a href="{{ route('orders.show', $return->order) }}"
               class="group flex items-center gap-4 rounded-2xl border border-savv-graylight bg-white p-4 transition hover:border-savv-orange/40 hover:shadow-sm">

                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-savv-orange/10 text-savv-orange">
                    <x-ui.icon name="undo" class="h-5 w-5" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-bold">Order #{{ $return->order->provider_order_id }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-savv-gray">
                        <x-ui.provider-chip :provider="$return->order->provider" />
                        <span>Requested {{ $return->requested_at?->format('d M Y') ?? 'unknown' }}</span>
                        @if ($return->provider_return_id)
                            <span class="text-savv-graylight">|</span>
                            <span>Ref {{ $return->provider_return_id }}</span>
                        @endif
                    </div>
                </div>

                <x-ui.badge :tone="StatusPresenter::tone($return->normalized_status)" dot class="shrink-0">
                    {{ $return->normalized_status->label() }}
                </x-ui.badge>

                <x-ui.icon name="chevron-right" class="hidden h-5 w-5 shrink-0 text-savv-graylight transition group-hover:text-savv-orange sm:block" />
            </a>
        @empty
            <div class="rounded-2xl border border-savv-graylight bg-white">
                <x-ui.empty
                    title="No returns recorded"
                    description="Returns appear here automatically when they show up in an imported order." />
            </div>
        @endforelse
    </div>

    @if ($returns->hasPages())
        <div class="mt-6">{{ $returns->links() }}</div>
    @endif

</x-layout>
