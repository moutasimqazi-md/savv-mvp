<x-layout title="Register - Savv MVP">
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 text-xl font-semibold">Register</h1>
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus
                       class="mt-1 w-full rounded border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="mt-1 w-full rounded border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Password</label>
                <input type="password" name="password" required minlength="12"
                       class="mt-1 w-full rounded border-gray-300">
                <p class="mt-1 text-xs text-gray-400">At least 12 characters.</p>
            </div>
            <div>
                <label class="block text-sm font-medium">Confirm password</label>
                <input type="password" name="password_confirmation" required
                       class="mt-1 w-full rounded border-gray-300">
            </div>
            <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 text-white">Create account</button>
        </form>
        <p class="mt-4 text-sm text-gray-500">
            Already registered? <a href="{{ route('login') }}" class="underline">Log in</a>
        </p>
    </div>
</x-layout>
