<x-layout title="Order {{ $order->provider_order_id }} - Savv MVP">
    <div class="mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-xl font-semibold">
                {{ ucfirst(str_replace('_', ' ', $order->provider->value)) }} - {{ $order->provider_order_id }}
            </h1>
            <p class="text-sm text-gray-500">
                Ordered {{ $order->ordered_at?->toFormattedDateString() ?? 'unknown date' }}
                - status {{ $order->normalized_status->label() }} (marketplace said "{{ $order->original_status ?? 'unknown' }}")
            </p>
        </div>
        <div class="flex gap-2">
            @if ($order->official_order_url)
                <a href="{{ $order->official_order_url }}" target="_blank" rel="noopener"
                   class="rounded border border-gray-300 px-3 py-2 text-sm">Open on marketplace</a>
            @endif
            <form method="POST" action="{{ route('orders.destroy', $order) }}"
                  onsubmit="return confirm('Delete this order from Savv MVP?');">
                @csrf @method('DELETE')
                <button type="submit" class="rounded border border-red-300 px-3 py-2 text-sm text-red-700">Delete</button>
            </form>
        </div>
    </div>

    <h2 class="mb-2 mt-6 font-semibold">Items</h2>
    <div class="divide-y divide-gray-200 rounded border border-gray-200 bg-white">
        @foreach ($order->items as $item)
            <div class="flex items-center justify-between px-4 py-3">
                <div>
                    <div class="font-medium">{{ $item->title }}</div>
                    @if ($item->variant)<div class="text-sm text-gray-500">{{ $item->variant }}</div>@endif
                    <div class="text-xs text-gray-400">Qty {{ $item->quantity }}</div>
                </div>
                <div class="text-right">
                    <div>₹{{ $item->lineTotalDecimal() }}</div>
                    @if ($item->official_product_url)
                        <a href="{{ $item->official_product_url }}" target="_blank" rel="noopener" class="text-xs underline">View product</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="mb-2 mt-6 font-semibold">Price breakdown</h2>
    <table class="w-full rounded border border-gray-200 bg-white text-sm">
        @foreach ([
            'Subtotal' => $order->subtotal_minor, 'Shipping' => $order->shipping_fee_minor,
            'Discount' => $order->discount_minor, 'Tax' => $order->tax_minor, 'Total' => $order->total_minor,
        ] as $label => $minor)
            <tr class="border-b border-gray-100 last:border-0">
                <td class="px-4 py-2">{{ $label }}</td>
                <td class="px-4 py-2 text-right">{{ $minor !== null ? '₹' . number_format($minor / 100, 2) : '-' }}</td>
            </tr>
        @endforeach
    </table>

    @if ($order->shipments->isNotEmpty())
        <h2 class="mb-2 mt-6 font-semibold">Shipment timeline</h2>
        @foreach ($order->shipments as $shipment)
            <div class="mb-3 rounded border border-gray-200 bg-white p-4">
                <div class="text-sm text-gray-500">
                    {{ $shipment->carrier ?? 'Carrier unknown' }}
                    @if ($shipment->tracking_number) - {{ $shipment->tracking_number }} @endif
                </div>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($shipment->events as $event)
                        <li>{{ $event->occurred_at?->toDayDateTimeString() }} - {{ $event->normalized_status?->label() ?? $event->original_status }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif

    @if ($order->returns->isNotEmpty())
        <h2 class="mb-2 mt-6 font-semibold">Return timeline</h2>
        @foreach ($order->returns as $return)
            <div class="mb-3 rounded border border-gray-200 bg-white p-4 text-sm">
                <div>Status: {{ $return->normalized_status->label() }} (marketplace: {{ $return->original_status ?? 'unknown' }})</div>
                <ul class="mt-2 space-y-1 text-gray-500">
                    @foreach ([
                        'Requested' => $return->requested_at, 'Approved' => $return->approved_at,
                        'Pickup scheduled' => $return->pickup_scheduled_at, 'Picked up' => $return->picked_up_at,
                        'Completed' => $return->completed_at,
                    ] as $label => $at)
                        @if ($at)<li>{{ $label }}: {{ $at->toDayDateTimeString() }}</li>@endif
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif

    @if ($order->refunds->isNotEmpty())
        <h2 class="mb-2 mt-6 font-semibold">Refund timeline</h2>
        @foreach ($order->refunds as $refund)
            <div class="mb-3 rounded border border-gray-200 bg-white p-4 text-sm">
                ₹{{ $refund->amountDecimal() }} - {{ $refund->normalized_status->label() }}
                @if ($refund->initiated_at) (initiated {{ $refund->initiated_at->toFormattedDateString() }}) @endif
            </div>
        @endforeach
    @endif

    @if ($order->invoices->isNotEmpty())
        <h2 class="mb-2 mt-6 font-semibold">Invoices</h2>
        <ul class="text-sm">
            @foreach ($order->invoices as $invoice)
                <li>
                    {{ $invoice->invoice_number ?? 'Invoice' }}
                    @if ($invoice->official_invoice_url)
                        - <a href="{{ $invoice->official_invoice_url }}" target="_blank" rel="noopener" class="underline">Download on marketplace</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <p class="mt-6 text-xs text-gray-400">
        Check return eligibility on Amazon/Flipkart.
        Imported via {{ $order->lastImportBatch?->source->value ?? 'unknown source' }},
        parser version {{ $order->parser_version ?? 'n/a' }},
        last observed {{ $order->source_observed_at?->diffForHumans() ?? 'unknown' }}.
    </p>
</x-layout>
