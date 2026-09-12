<x-layout title="Orders - Savv MVP">
    <h1 class="mb-6 text-xl font-semibold">Orders</h1>

    <form method="GET" class="mb-6 flex flex-wrap gap-3 text-sm">
        <select name="provider" class="rounded border-gray-300">
            <option value="">All marketplaces</option>
            <option value="amazon_in" @selected(request('provider') === 'amazon_in')>Amazon India</option>
            <option value="flipkart" @selected(request('provider') === 'flipkart')>Flipkart</option>
        </select>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Order, product, or tracking number"
               class="w-64 rounded border-gray-300">
        <input type="date" name="from" value="{{ request('from') }}" class="rounded border-gray-300">
        <input type="date" name="to" value="{{ request('to') }}" class="rounded border-gray-300">
        <select name="sort" class="rounded border-gray-300">
            @foreach ([
                'newest' => 'Newest', 'oldest' => 'Oldest', 'expected_delivery' => 'Expected delivery',
                'highest_total' => 'Highest total', 'lowest_total' => 'Lowest total', 'recently_updated' => 'Recently updated',
            ] as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white">Filter</button>
    </form>

    <div class="space-y-3">
        @forelse ($orders as $order)
            <div class="flex items-center justify-between rounded border border-gray-200 bg-white p-4">
                <div>
                    <a href="{{ route('orders.show', $order) }}" class="font-medium hover:underline">
                        {{ ucfirst(str_replace('_', ' ', $order->provider->value)) }} - {{ $order->provider_order_id }}
                    </a>
                    <div class="text-sm text-gray-500">{{ $order->items->first()?->title ?? 'No items recorded' }}</div>
                    <div class="text-xs text-gray-400">Last observed {{ $order->source_observed_at?->diffForHumans() ?? 'never' }}</div>
                </div>
                <div class="text-right">
                    <div class="font-medium">₹{{ $order->totalDecimal() }}</div>
                    <div class="text-xs text-gray-500">{{ $order->normalized_status->label() }}</div>
                    <a href="{{ route('orders.show', $order) }}" class="text-xs underline">View details</a>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No orders match these filters.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
</x-layout>
