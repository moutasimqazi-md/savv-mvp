<x-layout title="Dashboard - Savv MVP">
    <h1 class="mb-6 text-xl font-semibold">Dashboard</h1>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-6">
        @foreach ([
            'Total orders' => $summary['total_orders'] ?? 0,
            'Arriving soon' => $summary['arriving_soon'] ?? 0,
            'Active deliveries' => $summary['active_deliveries'] ?? 0,
            'Active returns' => $summary['active_returns'] ?? 0,
            'Pending refunds' => $summary['pending_refunds'] ?? 0,
            'Spending this month' => '₹' . number_format((($summary['spending_this_month_minor'] ?? 0)) / 100, 2),
        ] as $label => $value)
            <div class="rounded border border-gray-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ $value }}</div>
                <div class="text-xs text-gray-500">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 flex gap-3">
        <a href="{{ route('connections.index') }}" class="rounded bg-gray-900 px-4 py-2 text-sm text-white">Connect a marketplace</a>
        <a href="{{ route('orders.index') }}" class="rounded border border-gray-300 px-4 py-2 text-sm">View all orders</a>
    </div>

    <h2 class="mb-3 mt-10 text-lg font-semibold">Recent orders</h2>
    <div class="divide-y divide-gray-200 rounded border border-gray-200 bg-white">
        @forelse ($recentOrders as $order)
            <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                <div>
                    <div class="font-medium">{{ ucfirst(str_replace('_', ' ', $order->provider->value)) }} - {{ $order->provider_order_id }}</div>
                    <div class="text-sm text-gray-500">{{ $order->items->first()?->title ?? 'No items recorded' }}</div>
                </div>
                <div class="text-right">
                    <div class="font-medium">₹{{ $order->totalDecimal() }}</div>
                    <div class="text-xs text-gray-500">{{ $order->normalized_status->label() }}</div>
                </div>
            </a>
        @empty
            <div class="px-4 py-6 text-center text-sm text-gray-500">
                No orders yet. Connect a marketplace to import your first orders.
            </div>
        @endforelse
    </div>
</x-layout>
