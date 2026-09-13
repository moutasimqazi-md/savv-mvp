@props(['title' => 'Savv', 'split' => false, 'headline' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ asset('images/design/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800;900&family=Holtwood+One+SC&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white font-sans text-savv-darkgray antialiased">

@if ($split)
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Brand panel --}}
        <div class="relative hidden flex-col justify-between overflow-hidden bg-savv-orange p-10 text-white lg:flex">
            <div class="absolute -right-16 -top-16 h-72 w-72 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-24 -left-10 h-80 w-80 rounded-full bg-black/5"></div>

            <a href="{{ route('home') }}" class="relative flex items-center gap-2">
                <img src="{{ asset('images/design/logo.png') }}" alt="Savv" class="h-8 w-auto">
            </a>

            <div class="relative max-w-sm">
                <h2 class="font-display text-[26px] leading-snug">
                    {{ $headline ?? 'Every order and subscription, in one place.' }}
                </h2>

                <div class="mt-8 space-y-3 rounded-2xl bg-white p-4 text-savv-darkgray shadow-xl shadow-black/10">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/design/package.png') }}" alt="" class="h-8 w-8">
                            <div>
                                <div class="text-sm font-semibold">Amazon India</div>
                                <div class="text-xs text-savv-gray">Arriving tomorrow</div>
                            </div>
                        </div>
                        <span class="rounded-full bg-savv-orange/10 px-2.5 py-1 text-xs font-semibold text-orange-700">Out for delivery</span>
                    </div>
                    <div class="border-t border-savv-graylight"></div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/design/credit-card.png') }}" alt="" class="h-8 w-8">
                            <div>
                                <div class="text-sm font-semibold">Claude Pro</div>
                                <div class="text-xs text-savv-gray">Renews in 6 days</div>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-savv-blue">$20.00</span>
                    </div>
                </div>
            </div>

            <p class="relative max-w-sm text-xs leading-relaxed text-white/80">
                You log in to each site yourself, inside a temporary isolated browser.
                Savv never sees or stores your password, OTP, or session.
            </p>
        </div>

        {{-- Form panel --}}
        <div class="flex items-center justify-center px-5 py-12">
            <div class="w-full max-w-sm">
                <a href="{{ route('home') }}" class="mb-8 flex justify-center lg:hidden">
                    <img src="{{ asset('images/design/logo.png') }}" alt="Savv" class="h-9 w-auto">
                </a>
                {{ $slot }}
            </div>
        </div>
    </div>
@else
    <header class="border-b border-savv-graylight">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-5">
            <a href="{{ route('home') }}">
                <img src="{{ asset('images/design/logo.png') }}" alt="Savv" class="h-7 w-auto">
            </a>
            <div class="flex items-center gap-2">
                <x-ui.button :href="route('login')" variant="ghost" size="sm">Log in</x-ui.button>
                <x-ui.button :href="route('register')" variant="primary" size="sm">Get started</x-ui.button>
            </div>
        </div>
    </header>

    {{ $slot }}

    <footer class="border-t border-savv-graylight">
        <div class="mx-auto max-w-5xl px-5 py-8 text-xs leading-relaxed text-savv-gray">
            Demonstration import - verify order, delivery, return, refund, and subscription information on the
            official site. Not an official Amazon, Walmart, or Anthropic integration.
        </div>
    </footer>
@endif

</body>
</html>
