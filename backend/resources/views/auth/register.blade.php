<x-guest-layout title="Register - Savv" split headline="Start tracking your orders and subscriptions.">

    <h1 class="text-2xl font-bold tracking-tight">Create your account</h1>
    <p class="mt-1.5 text-sm text-savv-gray">No marketplace credentials needed - ever.</p>

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

    <form method="POST" action="{{ route('register') }}" class="mt-7 space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-xs font-bold uppercase tracking-wider text-savv-gray">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="mt-1.5 w-full rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
        </div>

        <div>
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-savv-gray">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                   class="mt-1.5 w-full rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
        </div>

        <div>
            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-savv-gray">Password</label>
            <input id="password" type="password" name="password" required minlength="12" autocomplete="new-password"
                   class="mt-1.5 w-full rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
            <p class="mt-1.5 text-xs text-savv-gray">At least 12 characters.</p>
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-savv-gray">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="mt-1.5 w-full rounded-xl border-savv-graylight text-sm focus:border-savv-orange focus:ring-savv-orange">
        </div>

        <x-ui.button type="submit" class="w-full">Create account</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-savv-gray">
        Already registered?
        <a href="{{ route('login') }}" class="font-semibold text-savv-orange hover:underline">Log in</a>
    </p>

</x-guest-layout>
