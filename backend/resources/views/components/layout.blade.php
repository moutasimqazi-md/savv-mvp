<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Savv MVP' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="font-semibold">Savv MVP</a>
            @auth
                <nav class="flex items-center gap-4 text-sm">
                    <a href="{{ route('dashboard') }}" class="hover:underline">Dashboard</a>
                    <a href="{{ route('orders.index') }}" class="hover:underline">Orders</a>
                    <a href="{{ route('returns.index') }}" class="hover:underline">Returns</a>
                    <a href="{{ route('refunds.index') }}" class="hover:underline">Refunds</a>
                    <a href="{{ route('connections.index') }}" class="hover:underline">Connections</a>
                    <a href="{{ route('privacy.index') }}" class="hover:underline">Privacy &amp; Data</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-500 hover:underline">Log out</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-5xl px-4 py-8 text-xs text-gray-400">
        Demonstration import - verify order, delivery, return and refund information on the official marketplace.
        Not an official Amazon or Flipkart integration.
    </footer>
</body>
</html>
