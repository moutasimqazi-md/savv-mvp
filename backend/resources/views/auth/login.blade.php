<x-guest-layout title="Log in - Savv" split headline="Welcome back. Everything is where you left it.">

    <h1 class="text-2xl font-bold tracking-tight">Log in</h1>
    <p class="mt-1.5 text-sm text-savv-gray">Enter your Savv account details to continue.</p>

    @if ($errors->any())
        <div class="mt-6 flex items-start gap-2.5 rounded-xl border border-savv-error/30 bg-savv-error/5 px-4 py-3 text-sm text-savv-error">
            <img src="{{ asset('images/design/error.png') }}" alt="" class="mt-0.5 h-5 w-5 shrink-0">
            <ul class="space-y-0.5 font-medium">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-savv-gray">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="mt-1.5 w-full rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
        </div>

        <div>
            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-savv-gray">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="mt-1.5 w-full rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
        </div>

        <label class="flex items-center gap-2 text-sm text-savv-darkgray/80">
            <input type="checkbox" name="remember" class="rounded border-savv-graylight text-savv-orange focus:ring-savv-orange">
            Remember me
        </label>

        <x-ui.button type="submit" class="w-full">Log in</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-savv-gray">
        No account?
        <a href="{{ route('register') }}" class="font-semibold text-savv-orange hover:underline">Create one</a>
    </p>

</x-guest-layout>
