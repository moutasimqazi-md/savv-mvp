@php
    use Savv\Support\Currency;
    use Savv\Support\StatusPresenter;

    $deliveryStep = StatusPresenter::deliveryStep($order->normalized_status);
@endphp

<x-layout title="Order {{ $order->provider_order_id }} - Savv">

    <a href="{{ route('orders.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-savv-gray transition hover:text-savv-darkgray">
        <x-ui.icon name="arrow-left" class="h-4 w-4" />
        Back to orders
    </a>

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.provider-chip :provider="$order->provider" />
                <x-ui.badge :tone="StatusPresenter::tone($order->normalized_status)" dot>
                    {{ $order->normalized_status->label() }}
                </x-ui.badge>
            </div>

            <h1 class="mt-3 text-2xl font-bold tracking-tight">Order #{{ $order->provider_order_id }}</h1>

            <p class="mt-1 text-sm text-savv-gray">
                Ordered {{ $order->ordered_at?->toFormattedDateString() ?? 'unknown date' }}
                @if ($order->original_status)
                    &middot; marketplace said &ldquo;{{ $order->original_status }}&rdquo;
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($order->official_order_url)
                <x-ui.button :href="$order->official_order_url" variant="outline" size="sm" target="_blank" rel="noopener">
                    <x-ui.icon name="external" class="h-4 w-4" />
                    Open on marketplace
                </x-ui.button>
            @endif
            <form method="POST" action="{{ route('orders.destroy', $order) }}"
                  onsubmit="return confirm('Delete this order from Savv?');">
                @csrf @method('DELETE')
                <x-ui.button type="submit" variant="danger" size="sm">
                    <x-ui.icon name="trash" class="h-4 w-4" />
                    Delete
                </x-ui.button>
            </form>
        </div>
    </div>

    {{-- Delivery progress --}}
    @if ($deliveryStep !== null)
        <x-ui.card class="mt-6" padding="px-5 py-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-bold">Delivery progress</h2>
                @if ($order->delivered_at)
                    <span class="text-xs font-semibold text-green-600">Delivered {{ $order->delivered_at->format('d M Y') }}</span>
                @elseif ($order->expected_delivery_at)
                    <span class="text-xs font-semibold text-savv-orange">Expected {{ $order->expected_delivery_at->format('d M Y') }}</span>
                @endif
            </div>
            <x-ui.stepper :steps="\Savv\Support\StatusPresenter::DELIVERY_STEPS" :current="$deliveryStep" />
        </x-ui.card>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-3">

        {{-- Left column --}}
        <div class="space-y-5 lg:col-span-2">

            {{-- Items --}}
            <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                <div class="border-b border-savv-graylight px-5 py-4">
                    <h2 class="text-sm font-bold">Items ({{ $order->items->count() }})</h2>
                </div>

                @foreach ($order->items as $item)
                    <div class="flex items-center gap-4 border-b border-savv-graylight px-5 py-4 last:border-0">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-savv-light ring-1 ring-inset ring-savv-graylight">
                            @if ($item->product_image_url)
                                <img src="{{ $item->product_image_url }}" alt="" class="h-full w-full object-cover">
                            @else
                                <img src="{{ asset('images/design/package.png') }}" alt="" class="h-7 w-7">
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold">{{ $item->title }}</div>
                            @if ($item->variant)
                                <div class="mt-0.5 text-xs text-savv-gray">{{ $item->variant }}</div>
                            @endif
                            <div class="mt-1 text-xs text-savv-gray">Qty {{ $item->quantity }}</div>
                        </div>

                        <div class="shrink-0 text-right">
                            <div class="text-sm font-bold">{{ Currency::format($item->line_total_minor, $order->currency) }}</div>
                            @if ($item->official_product_url)
                                <a href="{{ $item->official_product_url }}" target="_blank" rel="noopener"
                                   class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-savv-orange hover:underline">
                                    View product
                                    <x-ui.icon name="external" class="h-3 w-3" />
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Shipment timeline --}}
            @if ($order->shipments->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                    <div class="border-b border-savv-graylight px-5 py-4">
                        <h2 class="text-sm font-bold">Shipment timeline</h2>
                    </div>

                    @foreach ($order->shipments as $shipment)
                        <div class="border-b border-savv-graylight px-5 py-4 last:border-0">
                            <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                {{ $shipment->carrier ?? 'Carrier unknown' }}
                                @if ($shipment->tracking_number)
                                    <span class="rounded-lg bg-savv-light px-2 py-0.5 font-mono text-xs font-medium text-savv-gray">
                                        {{ $shipment->tracking_number }}
                                    </span>
                                @endif
                            </div>

                            <ol class="mt-4 space-y-0">
                                @foreach ($shipment->events as $event)
                                    <li class="relative flex gap-3 pb-4 last:pb-0">
                                        <div class="flex flex-col items-center">
                                            <span @class([
                                                'mt-1 h-2.5 w-2.5 shrink-0 rounded-full',
                                                'bg-savv-orange' => $loop->first,
                                                'bg-savv-graylight' => ! $loop->first,
                                            ])></span>
                                            @unless ($loop->last)
                                                <span class="mt-1 w-px flex-1 bg-savv-graylight"></span>
                                            @endunless
                                        </div>
                                        <div class="-mt-0.5 pb-1">
                                            <div class="text-sm font-medium">
                                                {{ $event->normalized_status?->label() ?? $event->original_status }}
                                            </div>
                                            <div class="mt-0.5 text-xs text-savv-gray">
                                                {{ $event->occurred_at?->toDayDateTimeString() }}
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Returns --}}
            @if ($order->returns->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                    <div class="border-b border-savv-graylight px-5 py-4">
                        <h2 class="text-sm font-bold">Returns</h2>
                    </div>

                    @foreach ($order->returns as $return)
                        <div class="border-b border-savv-graylight px-5 py-4 last:border-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <x-ui.badge :tone="StatusPresenter::tone($return->normalized_status)" dot>
                                    {{ $return->normalized_status->label() }}
                                </x-ui.badge>
                                @if ($return->original_status)
                                    <span class="text-xs text-savv-gray">marketplace: {{ $return->original_status }}</span>
                                @endif
                            </div>

                            <dl class="mt-3 grid gap-x-6 gap-y-1.5 text-xs sm:grid-cols-2">
                                @foreach ([
                                    'Requested' => $return->requested_at, 'Approved' => $return->approved_at,
                                    'Pickup scheduled' => $return->pickup_scheduled_at, 'Picked up' => $return->picked_up_at,
                                    'Completed' => $return->completed_at,
                                ] as $label => $at)
                                    @if ($at)
                                        <div class="flex justify-between gap-3 border-b border-savv-graylight/60 py-1">
                                            <dt class="text-savv-gray">{{ $label }}</dt>
                                            <dd class="font-medium">{{ $at->format('d M Y, H:i') }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Refunds --}}
            @if ($order->refunds->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                    <div class="border-b border-savv-graylight px-5 py-4">
                        <h2 class="text-sm font-bold">Refunds</h2>
                    </div>

                    @foreach ($order->refunds as $refund)
                        <div class="flex items-center justify-between border-b border-savv-graylight px-5 py-4 last:border-0">
                            <div>
                                <div class="text-sm font-bold">{{ Currency::format($refund->amount_minor, $refund->currency ?? $order->currency) }}</div>
                                @if ($refund->initiated_at)
                                    <div class="mt-0.5 text-xs text-savv-gray">Initiated {{ $refund->initiated_at->format('d M Y') }}</div>
                                @endif
                            </div>
                            <x-ui.badge :tone="StatusPresenter::tone($refund->normalized_status)" dot>
                                {{ $refund->normalized_status->label() }}
                            </x-ui.badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Right column --}}
        <div class="space-y-5">
            <x-ui.card>
                <h2 class="text-sm font-bold">Price breakdown</h2>
                <dl class="mt-4 space-y-2.5 text-sm">
                    @foreach ([
                        'Subtotal' => $order->subtotal_minor,
                        'Shipping' => $order->shipping_fee_minor,
                        'Discount' => $order->discount_minor,
                        'Tax' => $order->tax_minor,
                    ] as $label => $minor)
                        <div class="flex items-center justify-between">
                            <dt class="text-savv-gray">{{ $label }}</dt>
                            <dd class="font-medium">{{ Currency::format($minor, $order->currency) }}</dd>
                        </div>
                    @endforeach

                    <div class="flex items-center justify-between border-t border-savv-graylight pt-3">
                        <dt class="font-bold">Total</dt>
                        <dd class="text-lg font-bold text-savv-orange">{{ Currency::format($order->total_minor, $order->currency) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            @if ($order->invoices->isNotEmpty())
                <x-ui.card>
                    <h2 class="text-sm font-bold">Invoices</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($order->invoices as $invoice)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span class="truncate">{{ $invoice->invoice_number ?? 'Invoice' }}</span>
                                @if ($invoice->official_invoice_url)
                                    <a href="{{ $invoice->official_invoice_url }}" target="_blank" rel="noopener"
                                       class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-savv-orange hover:underline">
                                        <x-ui.icon name="download" class="h-3.5 w-3.5" />
                                        Download
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card padding="p-5" class="bg-savv-light">
                <h2 class="text-xs font-bold uppercase tracking-wider text-savv-gray">Import details</h2>
                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex justify-between gap-3">
                        <dt class="text-savv-gray">Source</dt>
                        <dd class="font-medium">{{ $order->lastImportBatch?->source->value ?? 'unknown' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-savv-gray">Parser version</dt>
                        <dd class="font-medium">{{ $order->parser_version ?? 'n/a' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-savv-gray">Last observed</dt>
                        <dd class="font-medium">{{ $order->source_observed_at?->diffForHumans() ?? 'unknown' }}</dd>
                    </div>
                </dl>
                <p class="mt-3 border-t border-savv-graylight pt-3 text-xs leading-relaxed text-savv-gray">
                    Check return eligibility on {{ $order->provider->label() }}.
                </p>
            </x-ui.card>
        </div>
    </div>

</x-layout>
