@php
    use Savv\Support\Currency;
    use Savv\Support\StatusPresenter;

    $tabs = collect([
        ['label' => 'All', 'value' => ''],
        ['label' => 'In transit', 'value' => 'in_transit'],
        ['label' => 'Delivered', 'value' => 'delivered'],
        ['label' => 'Returns', 'value' => 'returns'],
        ['label' => 'Refunds', 'value' => 'refunds'],
        ['label' => 'Cancelled', 'value' => 'cancelled'],
    ])->map(fn ($tab) => $tab + [
        'count' => $bucketCounts[$tab['value']] ?? 0,
        'url' => request()->fullUrlWithQuery(['bucket' => $tab['value'] ?: null, 'page' => null]),
    ])->all();

    $hasFilters = request()->hasAny(['q', 'provider', 'from', 'to']) && collect(request()->only(['q', 'provider', 'from', 'to']))->filter()->isNotEmpty();
@endphp

<x-layout title="Orders - Savv">

    <x-ui.page-header title="Orders" subtitle="Everything imported from your connected marketplaces.">
        <x-slot:actions>
            <x-ui.button :href="route('connections.index')" variant="accent" size="sm">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Import orders
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs :tabs="$tabs" :current="$bucket" />

    {{-- Filters --}}
    <form method="GET" class="mt-4 rounded-2xl border border-savv-graylight bg-white p-3">
        @if ($bucket)
            <input type="hidden" name="bucket" value="{{ $bucket }}">
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[220px] flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-savv-gray" />
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Order, product, or tracking number"
                       class="w-full rounded-xl border-savv-graylight pl-9 text-sm focus:border-savv-orange focus:ring-savv-orange">
            </div>

            <select name="provider" class="rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
                <option value="">All marketplaces</option>
                <option value="amazon_in" @selected(request('provider') === 'amazon_in')>Amazon India</option>
                <option value="walmart" @selected(request('provider') === 'walmart')>Walmart</option>
            </select>

            <input type="date" name="from" value="{{ request('from') }}" aria-label="From date"
                   class="rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
            <input type="date" name="to" value="{{ request('to') }}" aria-label="To date"
                   class="rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">

            <select name="sort" class="rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
                @foreach ([
                    'newest' => 'Newest first', 'oldest' => 'Oldest first', 'expected_delivery' => 'Expected delivery',
                    'highest_total' => 'Highest total', 'lowest_total' => 'Lowest total', 'recently_updated' => 'Recently updated',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <x-ui.button type="submit" size="sm">Apply</x-ui.button>

            @if ($hasFilters)
                <x-ui.button :href="route('orders.index', $bucket ? ['bucket' => $bucket] : [])" variant="ghost" size="sm">Clear</x-ui.button>
            @endif
        </div>
    </form>

    {{-- Results --}}
    <div class="mt-4 space-y-3">
        @forelse ($orders as $order)
            @php
                $item = $order->items->first();
                $extraItems = max($order->items->count() - 1, 0);
            @endphp

            <a href="{{ route('orders.show', $order) }}"
               class="group flex items-center gap-4 rounded-2xl border border-savv-graylight bg-white p-4 transition hover:border-savv-orange/40 hover:shadow-sm">

                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-savv-light ring-1 ring-inset ring-savv-graylight">
                    @if ($item?->product_image_url)
                        <img src="{{ $item->product_image_url }}" alt="" class="h-full w-full object-cover">
                    @else
                        <img src="{{ asset('images/design/package.png') }}" alt="" class="h-7 w-7">
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-bold">
                        {{ $item?->title ?? $order->provider->label() . ' order' }}
                        @if ($extraItems > 0)
                            <span class="font-medium text-savv-gray">+{{ $extraItems }} more</span>
                        @endif
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-savv-gray">
                        <x-ui.provider-chip :provider="$order->provider" />
                        <span>#{{ $order->provider_order_id }}</span>
                        @if ($order->ordered_at)
                            <span class="text-savv-graylight">|</span>
                            <span>Ordered {{ $order->ordered_at->format('d M Y') }}</span>
                        @endif
                    </div>

                    @if ($order->expected_delivery_at?->isFuture() && StatusPresenter::deliveryStep($order->normalized_status) !== null)
                        <div class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-savv-orange">
                            <x-ui.icon name="clock" class="h-3.5 w-3.5" />
                            Expected {{ $order->expected_delivery_at->format('d M') }}
                        </div>
                    @endif
                </div>

                <div class="shrink-0 space-y-1.5 text-right">
                    <div class="text-base font-bold">{{ Currency::format($order->total_minor, $order->currency) }}</div>
                    <x-ui.badge :tone="StatusPresenter::tone($order->normalized_status)" dot>
                        {{ $order->normalized_status->label() }}
                    </x-ui.badge>
                    <div class="text-[11px] text-savv-gray">
                        Updated {{ $order->source_observed_at?->diffForHumans() ?? 'never' }}
                    </div>
                </div>

                <x-ui.icon name="chevron-right" class="hidden h-5 w-5 shrink-0 text-savv-graylight transition group-hover:text-savv-orange sm:block" />
            </a>
        @empty
            <div class="rounded-2xl border border-savv-graylight bg-white">
                <x-ui.empty
                    title="No orders match these filters"
                    :description="$hasFilters || $bucket ? 'Try clearing the filters, or import more orders from a connected site.' : 'Connect Amazon India or Walmart to import your order history.'">
                    <x-slot:action>
                        @if ($hasFilters || $bucket)
                            <x-ui.button :href="route('orders.index')" variant="outline" size="sm">Clear filters</x-ui.button>
                        @else
                            <x-ui.button :href="route('connections.index')" variant="accent" size="sm">Connect a site</x-ui.button>
                        @endif
                    </x-slot:action>
                </x-ui.empty>
            </div>
        @endforelse
    </div>

    @if ($orders->hasPages())
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif

</x-layout>
