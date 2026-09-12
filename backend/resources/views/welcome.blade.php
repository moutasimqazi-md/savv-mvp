<x-layout title="Savv MVP">
    <div class="mx-auto max-w-xl py-16 text-center">
        <h1 class="text-3xl font-semibold">Savv MVP</h1>
        <p class="mt-4 text-gray-600">
            A demonstration dashboard for your own Amazon India and Flipkart orders,
            shipments, returns, and refunds. You log in to the marketplace yourself,
            in a temporary isolated browser - Savv never sees or stores your password,
            OTP, or session.
        </p>
        <p class="mt-2 text-xs text-gray-400">
            This is a demonstration, not an official Amazon or Flipkart integration.
        </p>
        <div class="mt-8 flex justify-center gap-4">
            <a href="{{ route('login') }}" class="rounded bg-gray-900 px-4 py-2 text-white">Log in</a>
            <a href="{{ route('register') }}" class="rounded border border-gray-300 px-4 py-2">Register</a>
        </div>
    </div>
</x-layout>
