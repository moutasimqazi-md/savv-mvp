<x-guest-layout title="Savv - Orders &amp; subscriptions in one place">

    {{-- Hero --}}
    <section class="mx-auto max-w-5xl px-5 pb-4 pt-12 sm:pt-16">
        <div class="relative overflow-hidden rounded-[28px] bg-savv-orange px-6 py-12 text-center text-white shadow-xl shadow-savv-orange/20 sm:px-12 sm:py-16">
            <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-24 -left-16 h-72 w-72 rounded-full bg-black/5"></div>

            <div class="relative">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-[11px] font-bold uppercase tracking-widest">
                    Demonstration build
                </span>

                <h1 class="mx-auto mt-5 max-w-2xl font-display text-3xl leading-[1.25] sm:text-[40px] sm:leading-[1.2]">
                    Effortless orders &amp; subscription tracking
                </h1>

                <p class="mx-auto mt-5 max-w-xl text-sm leading-relaxed text-white/90 sm:text-base">
                    Bring your own Amazon India and Walmart order history together with subscriptions
                    like Claude Pro - imported by you, in a temporary isolated browser.
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-full bg-black px-6 py-3 text-sm font-semibold text-white transition hover:bg-savv-darkgray">
                        Get started free
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-6 py-3 text-sm font-semibold text-white ring-1 ring-inset ring-white/30 transition hover:bg-white/25">
                        I already have an account
                    </a>
                </div>

                {{-- Floating preview card --}}
                <div class="mx-auto mt-12 max-w-md rounded-2xl bg-white p-4 text-left text-savv-darkgray shadow-2xl shadow-black/20">
                    <div class="flex items-center justify-between pb-3">
                        <span class="text-[11px] font-bold uppercase tracking-widest text-savv-gray">Your activity</span>
                        <span class="rounded-full bg-savv-blue/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-savv-blue">Live</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-t border-savv-graylight pt-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('images/design/package.png') }}" alt="" class="h-9 w-9">
                                <div>
                                    <div class="text-sm font-semibold">Amazon India</div>
                                    <div class="text-xs text-savv-gray">Order #12-3456789</div>
                                </div>
                            </div>
                            <span class="rounded-full bg-savv-orange/10 px-2.5 py-1 text-xs font-semibold text-orange-700">Delivered</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-savv-graylight pt-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('images/design/package.png') }}" alt="" class="h-9 w-9">
                                <div>
                                    <div class="text-sm font-semibold">Walmart</div>
                                    <div class="text-xs text-savv-gray">Arriving Thursday</div>
                                </div>
                            </div>
                            <span class="rounded-full bg-savv-blue/10 px-2.5 py-1 text-xs font-semibold text-savv-blue">In transit</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-savv-graylight pt-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('images/design/credit-card.png') }}" alt="" class="h-9 w-9">
                                <div>
                                    <div class="text-sm font-semibold">Claude Pro</div>
                                    <div class="text-xs text-savv-gray">Renews 28 May</div>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-savv-blue">$20.00<span class="text-xs font-medium text-savv-gray">/mo</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Feature grid --}}
    <section class="mx-auto max-w-5xl px-5 py-16">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['image' => 'package.png', 'title' => 'Order tracking', 'body' => 'Deliveries, returns and refunds from Amazon India and Walmart, normalised into one timeline.'],
                ['image' => 'credit-card.png', 'title' => 'Subscription clarity', 'body' => 'See what renews next and what it costs, alongside everything else you spend.'],
                ['image' => 'hero-badge.png', 'title' => 'You stay in control', 'body' => 'No stored passwords, OTPs, cookies or sessions. Export or delete everything at any time.'],
            ] as $feature)
                <div class="rounded-2xl border border-savv-graylight bg-white p-6">
                    <img src="{{ asset('images/design/' . $feature['image']) }}" alt="" class="h-10 w-auto">
                    <h3 class="mt-4 text-base font-bold">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-savv-gray">{{ $feature['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto max-w-5xl px-5 pb-20">
        <div class="rounded-[28px] border border-savv-graylight bg-savv-light px-6 py-12 sm:px-12">
            <h2 class="text-center text-xl font-bold tracking-tight">How an import works</h2>
            <p class="mx-auto mt-2 max-w-lg text-center text-sm text-savv-gray">
                Four steps, every time. Savv opens the browser - you do the logging in.
            </p>

            <div class="mt-10 grid gap-6 sm:grid-cols-4">
                @foreach ([
                    ['step' => '01', 'title' => 'Pick a site', 'body' => 'Choose Amazon India, Walmart or Claude and accept the consent notice.'],
                    ['step' => '02', 'title' => 'Log in yourself', 'body' => 'A temporary isolated browser opens. Your credentials go only to that site.'],
                    ['step' => '03', 'title' => 'Scan', 'body' => 'Savv reads the page you are already looking at - nothing else.'],
                    ['step' => '04', 'title' => 'Review & import', 'body' => 'You approve each row before anything is saved to your account.'],
                ] as $item)
                    <div>
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-savv-orange text-xs font-black text-white">{{ $item['step'] }}</div>
                        <h3 class="mt-3 text-sm font-bold">{{ $item['title'] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-savv-gray">{{ $item['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-full bg-black px-6 py-3 text-sm font-semibold text-white transition hover:bg-savv-darkgray">
                    Create your account
                </a>
                <p class="mt-3 text-xs text-savv-gray">
                    This is a demonstration, not an official Amazon, Walmart, or Anthropic integration.
                </p>
            </div>
        </div>
    </section>

</x-guest-layout>
