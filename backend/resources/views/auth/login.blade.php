<x-layout title="Log in - Savv MVP">
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 text-xl font-semibold">Log in</h1>
        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 w-full rounded border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Password</label>
                <input type="password" name="password" required class="mt-1 w-full rounded border-gray-300">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember"> Remember me
            </label>
            <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 text-white">Log in</button>
        </form>
        <p class="mt-4 text-sm text-gray-500">
            No account? <a href="{{ route('register') }}" class="underline">Register</a>
        </p>
    </div>
</x-layout>
