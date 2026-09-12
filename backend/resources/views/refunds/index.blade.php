<x-layout title="Refunds - Savv MVP">
    <h1 class="mb-6 text-xl font-semibold">Refunds</h1>

    <div class="space-y-3">
        @forelse ($refunds as $refund)
            <div class="flex items-center justify-between rounded border border-gray-200 bg-white p-4">
                <div>
                    <a href="{{ route('orders.show', $refund->order) }}" class="font-medium hover:underline">
                        {{ $refund->order->provider_order_id }}
                    </a>
                    <div class="text-sm text-gray-500">Initiated {{ $refund->initiated_at?->toFormattedDateString() ?? 'unknown' }}</div>
                </div>
                <div class="text-right">
                    <div class="font-medium">₹{{ $refund->amountDecimal() }}</div>
                    <div class="text-sm text-gray-500">{{ $refund->normalized_status->label() }}</div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No refunds recorded.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $refunds->links() }}</div>
</x-layout>
