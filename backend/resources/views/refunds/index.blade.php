@php
    use Savv\Support\Currency;
    use Savv\Support\StatusPresenter;
@endphp

<x-layout title="Refunds - Savv">

    <x-ui.page-header title="Refunds" subtitle="Money coming back to you from imported orders." />

    <div class="space-y-3">
        @forelse ($refunds as $refund)
            <a href="{{ route('orders.show', $refund->order) }}"
               class="group flex items-center gap-4 rounded-2xl border border-savv-graylight bg-white p-4 transition hover:border-savv-orange/40 hover:shadow-sm">

                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-savv-green/10 text-green-600">
                    <x-ui.icon name="receipt" class="h-5 w-5" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-bold">Order #{{ $refund->order->provider_order_id }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-savv-gray">
                        <x-ui.provider-chip :provider="$refund->order->provider" />
                        <span>Initiated {{ $refund->initiated_at?->format('d M Y') ?? 'unknown' }}</span>
                        @if ($refund->completed_at)
                            <span class="text-savv-graylight">|</span>
                            <span>Completed {{ $refund->completed_at->format('d M Y') }}</span>
                        @endif
                    </div>
                </div>

                <div class="shrink-0 space-y-1.5 text-right">
                    <div class="text-base font-bold">
                        {{ Currency::format($refund->amount_minor, $refund->currency ?? $refund->order->currency) }}
                    </div>
                    <x-ui.badge :tone="StatusPresenter::tone($refund->normalized_status)" dot>
                        {{ $refund->normalized_status->label() }}
                    </x-ui.badge>
                </div>

                <x-ui.icon name="chevron-right" class="hidden h-5 w-5 shrink-0 text-savv-graylight transition group-hover:text-savv-orange sm:block" />
            </a>
        @empty
            <div class="rounded-2xl border border-savv-graylight bg-white">
                <x-ui.empty
                    title="No refunds recorded"
                    description="Refunds appear here automatically when they show up in an imported order." />
            </div>
        @endforelse
    </div>

    @if ($refunds->hasPages())
        <div class="mt-6">{{ $refunds->links() }}</div>
    @endif

</x-layout>
