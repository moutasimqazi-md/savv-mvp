@php
    use Savv\Enums\BillingCycle;
    use Savv\Enums\SubscriptionStatus;
    use Savv\Support\Currency;
    use Savv\Support\StatusPresenter;

    $active = $subscriptions->filter(fn ($s) => $s->normalized_status === SubscriptionStatus::Active);

    // Normalise every active plan to a monthly figure so the total is comparable.
    $monthlyMinor = $active->sum(fn ($s) => match ($s->billing_cycle) {
        BillingCycle::Yearly => (int) round(((int) $s->price_minor) / 12),
        default => (int) $s->price_minor,
    });

    $currency = $subscriptions->first()?->currency ?? 'USD';
    $nextRenewal = $active->whereNotNull('renewal_at')->sortBy('renewal_at')->first();
@endphp

<x-layout title="Subscriptions - Savv">

    <x-ui.page-header title="Subscriptions" subtitle="Recurring plans imported from your connected accounts.">
        <x-slot:actions>
            <x-ui.button :href="route('connections.index')" variant="accent" size="sm">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Import subscription
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($subscriptions->isNotEmpty())
        {{-- Summary hero --}}
        <div class="relative overflow-hidden rounded-[24px] bg-savv-blue px-6 py-7 text-white shadow-lg shadow-savv-blue/20 sm:px-8">
            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10"></div>

            <div class="relative grid gap-6 sm:grid-cols-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-white/80">Monthly equivalent</p>
                    <p class="mt-2 font-display text-3xl leading-none">{{ Currency::format($monthlyMinor, $currency) }}</p>
                    <p class="mt-2 text-xs text-white/80">Across {{ $active->count() }} active {{ Str::plural('plan', $active->count()) }}</p>
                </div>

                <div class="sm:border-l sm:border-white/20 sm:pl-6">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-white/80">Next renewal</p>
                    @if ($nextRenewal)
                        <p class="mt-2 text-lg font-bold leading-tight">{{ $nextRenewal->plan_name }}</p>
                        <p class="mt-1 text-xs text-white/80">{{ $nextRenewal->renewal_at->diffForHumans() }}</p>
                    @else
                        <p class="mt-2 text-sm text-white/80">Nothing scheduled</p>
                    @endif
                </div>

                <div class="sm:border-l sm:border-white/20 sm:pl-6">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-white/80">Yearly projection</p>
                    <p class="mt-2 text-lg font-bold leading-tight">{{ Currency::format($monthlyMinor * 12, $currency) }}</p>
                    <p class="mt-1 text-xs text-white/80">If nothing changes</p>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @forelse ($subscriptions as $subscription)
            <div class="rounded-2xl border border-savv-graylight bg-white p-5 transition hover:border-savv-blue/40 hover:shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-inset ring-savv-graylight">
                            <img src="{{ asset('images/design/credit-card.png') }}" alt="" class="h-6 w-6">
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold">{{ $subscription->plan_name }}</div>
                            <div class="mt-0.5 text-xs text-savv-gray">{{ $subscription->provider->label() }}</div>
                        </div>
                    </div>

                    <x-ui.badge :tone="StatusPresenter::tone($subscription->normalized_status)" dot>
                        {{ $subscription->normalized_status->label() }}
                    </x-ui.badge>
                </div>

                <div class="mt-5 flex items-end justify-between gap-3 border-t border-savv-graylight pt-4">
                    <div>
                        @if ($subscription->priceDecimal())
                            <div class="text-xl font-bold text-savv-blue">
                                {{ Currency::format($subscription->price_minor, $subscription->currency) }}
                                <span class="text-xs font-medium text-savv-gray">/ {{ strtolower($subscription->billing_cycle->label()) }}</span>
                            </div>
                        @else
                            <div class="text-sm text-savv-gray">Price unknown</div>
                        @endif

                        @if ($subscription->renewal_at)
                            <div class="mt-1.5 inline-flex items-center gap-1.5 text-xs text-savv-gray">
                                <x-ui.icon name="clock" class="h-3.5 w-3.5" />
                                {{ $subscription->renewal_at->isPast() ? 'Renewed' : 'Renews' }}
                                {{ $subscription->renewal_at->format('d M Y') }}
                            </div>
                        @endif
                    </div>

                    @if ($subscription->official_billing_url)
                        <a href="{{ $subscription->official_billing_url }}" target="_blank" rel="noopener"
                           class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-savv-blue hover:underline">
                            Manage
                            <x-ui.icon name="external" class="h-3 w-3" />
                        </a>
                    @endif
                </div>

                <div class="mt-3 text-[11px] text-savv-gray">
                    Last observed {{ $subscription->source_observed_at?->diffForHumans() ?? 'never' }}
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-savv-graylight bg-white sm:col-span-2">
                <x-ui.empty
                    title="No subscriptions yet"
                    description="Connect Claude to import your plan, price and renewal date."
                    image="credit-card.png">
                    <x-slot:action>
                        <x-ui.button :href="route('connections.index')" variant="accent" size="sm">Connect a site</x-ui.button>
                    </x-slot:action>
                </x-ui.empty>
            </div>
        @endforelse
    </div>

</x-layout>
