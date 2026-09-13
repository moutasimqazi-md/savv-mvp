<x-layout title="Connections - Savv">

    <x-ui.page-header title="Connections" subtitle="Import your own data from a site, in a temporary isolated browser." />

    @if ($activeImportSession)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-savv-orange/30 bg-savv-orange/5 px-4 py-3">
            <div class="flex items-start gap-2.5 text-sm">
                <img src="{{ asset('images/design/spinner.png') }}" alt="" class="mt-0.5 h-4 w-4 animate-spin">
                <span>You already have an import session running. Finish or cancel it before starting another.</span>
            </div>
            <x-ui.button :href="route('imports.show', $activeImportSession)" size="sm">Continue import</x-ui.button>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($providers as $provider)
            @php
                $connection = $connections->get($provider->value);
                $isSubscription = $provider->kind()->value === 'subscription';
            @endphp

            <div class="flex flex-col rounded-2xl border border-savv-graylight bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white ring-1 ring-inset ring-savv-graylight">
                        <img src="{{ asset('images/design/' . ($isSubscription ? 'credit-card.png' : 'package.png')) }}" alt="" class="h-7 w-7">
                    </div>

                    <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white {{ $isSubscription ? 'bg-savv-blue' : 'bg-savv-orange' }}">
                        {{ $isSubscription ? 'Subscription' : 'Orders' }}
                    </span>
                </div>

                <h2 class="mt-4 text-base font-bold">{{ $provider->label() }}</h2>

                <div class="mt-1 flex items-center gap-1.5 text-xs">
                    <span class="h-1.5 w-1.5 rounded-full {{ $connection ? 'bg-savv-green' : 'bg-savv-gray' }}"></span>
                    <span class="text-savv-gray">{{ $connection ? 'Previously connected' : 'Not yet connected' }}</span>
                </div>

                <details class="group mt-4 text-sm">
                    <summary class="flex cursor-pointer list-none items-center gap-1 text-xs font-semibold text-savv-darkgray [&::-webkit-details-marker]:hidden">
                        What Savv will do
                        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 transition group-open:rotate-90" />
                    </summary>
                    <p class="mt-2 text-xs leading-relaxed text-savv-gray">
                        Savv opens a temporary remote browser. You log into {{ $provider->label() }} yourself
                        inside it. Savv does not intentionally store your password or OTP, and the browser and
                        its profile are deleted when the import ends. This demonstration is not an official
                        Amazon, Walmart, or Anthropic integration.
                    </p>
                </details>

                <form method="POST" action="{{ route('connections.start', $provider->value) }}" class="mt-auto pt-5">
                    @csrf
                    <label class="flex items-start gap-2 text-xs leading-relaxed text-savv-darkgray/80">
                        <input type="checkbox" name="accept_consent" value="1" required
                               class="mt-0.5 rounded border-savv-graylight text-savv-orange focus:ring-savv-orange">
                        <span>
                            I want to import my {{ $provider->label() }}
                            {{ $isSubscription ? 'subscription' : 'order' }} information.
                        </span>
                    </label>

                    <x-ui.button type="submit" :disabled="(bool) $activeImportSession" size="sm" class="mt-3 w-full">
                        Start temporary browser
                    </x-ui.button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-savv-graylight bg-white p-6">
        <h2 class="text-sm font-bold">What happens during an import</h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-4">
            @foreach ([
                ['01', 'Browser opens', 'A temporary, isolated Chromium session starts just for you.'],
                ['02', 'You log in', 'Credentials and OTPs go straight to the site - never to Savv.'],
                ['03', 'Savv scans', 'Only the order or billing page you are already viewing is read.'],
                ['04', 'You approve', 'Nothing is saved until you review the preview and confirm.'],
            ] as [$step, $stepTitle, $body])
                <div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-savv-orange text-[11px] font-black text-white">{{ $step }}</div>
                    <h3 class="mt-2.5 text-xs font-bold">{{ $stepTitle }}</h3>
                    <p class="mt-1 text-xs leading-relaxed text-savv-gray">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </div>

</x-layout>
