@php
    use Savv\Support\Currency;
    use Savv\Support\StatusPresenter;

    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

<x-layout title="Dashboard - Savv">

    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-[24px] bg-savv-orange px-6 py-7 text-white shadow-lg shadow-savv-orange/20 sm:px-8">
        <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-24 right-24 h-52 w-52 rounded-full bg-black/5"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-6">
            <div class="max-w-lg">
                <p class="text-[11px] font-bold uppercase tracking-widest text-white/80">{{ $greeting }}</p>
                <h1 class="mt-1.5 font-display text-2xl leading-snug">{{ auth()->user()->name }}</h1>
                <p class="mt-3 text-sm leading-relaxed text-white/90">
                    @if (($summary['total_orders'] ?? 0) === 0)
                        You have not imported anything yet. Connect a site to bring in your first orders.
                    @else
                        You are tracking <strong class="font-bold">{{ $summary['total_orders'] }}</strong> orders,
                        with <strong class="font-bold">{{ $summary['active_deliveries'] ?? 0 }}</strong> on the way.
                    @endif
                </p>

                <div class="mt-5 flex flex-wrap gap-2.5">
                    <a href="{{ route('connections.index') }}" class="inline-flex items-center gap-2 rounded-full bg-black px-4 py-2 text-sm font-semibold text-white transition hover:bg-savv-darkgray">
                        <x-ui.icon name="plus" class="h-4 w-4" />
                        New import
                    </a>
                    <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-inset ring-white/30 transition hover:bg-white/25">
                        View all orders
                    </a>
                </div>
            </div>

        </div>
    </div>

    {{-- Stats --}}
    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-ui.stat label="Total orders" :value="$summary['total_orders'] ?? 0" />
        <x-ui.stat label="Arriving soon" :value="$summary['arriving_soon'] ?? 0" tone="accent" hint="Next 3 days" />
        <x-ui.stat label="In transit" :value="$summary['active_deliveries'] ?? 0" tone="info" />
        <x-ui.stat label="Active returns" :value="$summary['active_returns'] ?? 0" />
        <x-ui.stat
            label="Spent this month"
            :value="Currency::format($summary['spending_this_month_minor'] ?? 0, $spendingCurrency)"
            tone="accent"
            class="col-span-2 lg:col-span-1" />
    </div>

    {{-- Content --}}
    <div class="mt-6 grid gap-5 lg:grid-cols-3">

        {{-- Recent orders --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                <div class="flex items-center justify-between border-b border-savv-graylight px-5 py-4">
                    <h2 class="flex items-center gap-2 text-sm font-bold">
                        <img src="{{ asset('images/design/package.png') }}" alt="" class="h-5 w-5">
                        Recent orders
                    </h2>
                    <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-savv-orange hover:underline">
                        View all
                        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5" />
                    </a>
                </div>

                @forelse ($recentOrders as $order)
                    <a href="{{ route('orders.show', $order) }}" class="flex items-center gap-4 border-b border-savv-graylight px-5 py-3.5 transition last:border-0 hover:bg-savv-light">
                        @php $thumb = $order->items->first()?->product_image_url; @endphp
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-savv-light ring-1 ring-inset ring-savv-graylight">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="" class="h-full w-full object-cover">
                            @else
                                <img src="{{ asset('images/design/package.png') }}" alt="" class="h-6 w-6">
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">
                                {{ $order->items->first()?->title ?? $order->provider->label() . ' order' }}
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-savv-gray">
                                <span>{{ $order->provider->label() }}</span>
                                <span class="text-savv-graylight">|</span>
                                <span>#{{ $order->provider_order_id }}</span>
                                @if ($order->ordered_at)
                                    <span class="text-savv-graylight">|</span>
                                    <span>{{ $order->ordered_at->format('d M Y') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <div class="text-sm font-bold">{{ Currency::format($order->total_minor, $order->currency) }}</div>
                            <x-ui.badge :tone="StatusPresenter::tone($order->normalized_status)" dot class="mt-1">
                                {{ $order->normalized_status->label() }}
                            </x-ui.badge>
                        </div>
                    </a>
                @empty
                    <x-ui.empty
                        title="No orders yet"
                        description="Connect Amazon India or Walmart to import your order history into Savv.">
                        <x-slot:action>
                            <x-ui.button :href="route('connections.index')" variant="accent" size="sm">Connect a site</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty>
                @endforelse
            </div>
        </div>

        {{-- Side column --}}
        <div class="space-y-5">
            <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                <div class="flex items-center justify-between border-b border-savv-graylight px-5 py-4">
                    <h2 class="flex items-center gap-2 text-sm font-bold">
                        <img src="{{ asset('images/design/credit-card.png') }}" alt="" class="h-5 w-5">
                        Upcoming renewals
                    </h2>
                    <a href="{{ route('subscriptions.index') }}" class="text-xs font-semibold text-savv-blue hover:underline">All</a>
                </div>

                @forelse ($upcomingRenewals as $subscription)
                    <div class="flex items-center justify-between border-b border-savv-graylight px-5 py-3.5 last:border-0">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold">{{ $subscription->plan_name }}</div>
                            <div class="mt-0.5 text-xs text-savv-gray">
                                {{ $subscription->renewal_at->isPast() ? 'Renewed' : 'Renews' }}
                                {{ $subscription->renewal_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="shrink-0 pl-3 text-right">
                            @if ($subscription->priceDecimal())
                                <div class="text-sm font-bold text-savv-blue">
                                    {{ Currency::format($subscription->price_minor, $subscription->currency) }}
                                </div>
                                <div class="text-[11px] text-savv-gray">{{ $subscription->billing_cycle->label() }}</div>
                            @else
                                <div class="text-xs text-savv-gray">Price unknown</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center">
                        <p class="text-sm text-savv-gray">No subscriptions tracked yet.</p>
                        <a href="{{ route('connections.index') }}" class="mt-2 inline-block text-xs font-semibold text-savv-blue hover:underline">Import one</a>
                    </div>
                @endforelse
            </div>

            <div class="rounded-2xl border border-savv-graylight bg-white p-5">
                <h2 class="text-sm font-bold">Needs attention</h2>
                <div class="mt-3 space-y-2">
                    <a href="{{ route('returns.index') }}" class="flex items-center justify-between rounded-xl bg-savv-light px-3.5 py-2.5 transition hover:bg-savv-graylight">
                        <span class="text-sm font-medium">Active returns</span>
                        <span class="text-sm font-bold">{{ $summary['active_returns'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('refunds.index') }}" class="flex items-center justify-between rounded-xl bg-savv-light px-3.5 py-2.5 transition hover:bg-savv-graylight">
                        <span class="text-sm font-medium">Pending refunds</span>
                        <span class="text-sm font-bold">{{ $summary['pending_refunds'] ?? 0 }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

</x-layout>
