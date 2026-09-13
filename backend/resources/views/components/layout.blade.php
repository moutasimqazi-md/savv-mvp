@php
    $navGroups = [
        'Overview' => [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid'],
        ],
        'Shopping' => [
            ['route' => 'orders.index', 'label' => 'Orders', 'icon' => 'box', 'pattern' => 'orders.*'],
            ['route' => 'returns.index', 'label' => 'Returns', 'icon' => 'undo'],
            ['route' => 'refunds.index', 'label' => 'Refunds', 'icon' => 'receipt'],
            ['route' => 'subscriptions.index', 'label' => 'Subscriptions', 'icon' => 'card'],
        ],
        'Account' => [
            ['route' => 'connections.index', 'label' => 'Connections', 'icon' => 'plug', 'pattern' => 'connections.*'],
            ['route' => 'privacy.index', 'label' => 'Privacy & Data', 'icon' => 'shield'],
        ],
    ];
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Savv' }}</title>
    <link rel="icon" href="{{ asset('images/design/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800;900&family=Holtwood+One+SC&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-savv-light font-sans text-savv-darkgray antialiased">
<div class="flex min-h-screen">

    {{-- Sidebar (desktop) --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-savv-graylight bg-white lg:flex">
        <div class="flex h-16 items-center px-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/design/logo.png') }}" alt="Savv" class="h-7 w-auto">
            </a>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            @foreach ($navGroups as $groupLabel => $links)
                <div>
                    <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-widest text-savv-gray">{{ $groupLabel }}</div>
                    <div class="space-y-0.5">
                        @foreach ($links as $link)
                            @php $active = request()->routeIs($link['pattern'] ?? $link['route']); @endphp
                            <a href="{{ route($link['route']) }}"
                               @class([
                                   'group relative flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold transition',
                                   'bg-savv-orange/10 text-savv-orange' => $active,
                                   'text-savv-darkgray/70 hover:bg-savv-light hover:text-savv-darkgray' => ! $active,
                               ])>
                                @if ($active)
                                    <span class="absolute left-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-r-full bg-savv-orange"></span>
                                @endif
                                <x-ui.icon :name="$link['icon']" class="h-[18px] w-[18px] shrink-0" />
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-savv-graylight p-3">
            <a href="{{ route('connections.index') }}" class="mb-3 flex items-center gap-2 rounded-xl bg-black px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-savv-darkgray">
                <x-ui.icon name="plus" class="h-4 w-4" />
                New import
            </a>
            <div class="flex items-center gap-3 rounded-xl px-2 py-1.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-savv-orange text-sm font-bold text-white">
                    {{ strtoupper(substr($user?->name ?? 'S', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">{{ $user?->name }}</div>
                    <div class="truncate text-xs text-savv-gray">{{ $user?->email }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Log out" class="rounded-lg p-1.5 text-savv-gray transition hover:bg-savv-light hover:text-savv-error">
                        <x-ui.icon name="logout" class="h-4 w-4" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main column --}}
    <div class="flex min-h-screen flex-1 flex-col lg:pl-64">

        {{-- Mobile top bar --}}
        <header class="sticky top-0 z-20 border-b border-savv-graylight bg-white/90 backdrop-blur lg:hidden">
            <details class="group">
                <summary class="flex h-16 cursor-pointer list-none items-center justify-between px-4 [&::-webkit-details-marker]:hidden">
                    <img src="{{ asset('images/design/logo.png') }}" alt="Savv" class="h-6 w-auto">
                    <span class="rounded-lg p-2 text-savv-darkgray">
                        <x-ui.icon name="menu" class="h-5 w-5" />
                    </span>
                </summary>
                <nav class="space-y-4 border-t border-savv-graylight px-3 py-4">
                    @foreach ($navGroups as $groupLabel => $links)
                        <div>
                            <div class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-savv-gray">{{ $groupLabel }}</div>
                            @foreach ($links as $link)
                                @php $active = request()->routeIs($link['pattern'] ?? $link['route']); @endphp
                                <a href="{{ route($link['route']) }}"
                                   @class([
                                       'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold',
                                       'bg-savv-orange/10 text-savv-orange' => $active,
                                       'text-savv-darkgray/70' => ! $active,
                                   ])>
                                    <x-ui.icon :name="$link['icon']" class="h-[18px] w-[18px]" />
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}" class="px-3 pt-2">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-0 py-2 text-sm font-semibold text-savv-error">
                            <x-ui.icon name="logout" class="h-[18px] w-[18px]" />
                            Log out
                        </button>
                    </form>
                </nav>
            </details>
        </header>

        <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
            <div class="mx-auto max-w-6xl">
                @if (session('status'))
                    <div class="mb-5 flex items-center gap-2.5 rounded-xl border border-savv-green/30 bg-savv-green/10 px-4 py-3 text-sm font-medium text-green-800">
                        <img src="{{ asset('images/design/check-circle.png') }}" alt="" class="h-5 w-5 shrink-0">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-savv-error/30 bg-savv-error/5 px-4 py-3 text-sm text-savv-error">
                        <img src="{{ asset('images/design/error.png') }}" alt="" class="mt-0.5 h-5 w-5 shrink-0">
                        <ul class="space-y-0.5 font-medium">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>

        <footer class="px-4 pb-8 lg:px-8">
            <div class="mx-auto max-w-6xl border-t border-savv-graylight pt-5 text-xs leading-relaxed text-savv-gray">
                Demonstration import - verify order, delivery, return, refund, and subscription information on the
                official site. Not an official Amazon, Walmart, or Anthropic integration.
            </div>
        </footer>
    </div>
</div>
</body>
</html>
