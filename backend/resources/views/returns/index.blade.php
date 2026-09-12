<x-layout title="Returns - Savv MVP">
    <h1 class="mb-6 text-xl font-semibold">Returns</h1>

    <div class="space-y-3">
        @forelse ($returns as $return)
            <div class="flex items-center justify-between rounded border border-gray-200 bg-white p-4">
                <div>
                    <a href="{{ route('orders.show', $return->order) }}" class="font-medium hover:underline">
                        {{ $return->order->provider_order_id }}
                    </a>
                    <div class="text-sm text-gray-500">Requested {{ $return->requested_at?->toFormattedDateString() ?? 'unknown' }}</div>
                </div>
                <div class="text-sm">{{ $return->normalized_status->label() }}</div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No returns recorded.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $returns->links() }}</div>
</x-layout>
