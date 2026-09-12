<x-layout title="Connections - Savv MVP">
    <h1 class="mb-6 text-xl font-semibold">Connections</h1>

    @if ($activeImportSession)
        <div class="mb-6 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
            You already have an active import session.
            <a href="{{ route('imports.show', $activeImportSession) }}" class="underline">Continue it</a>
            before starting another.
        </div>
    @endif

    <div class="space-y-6">
        @foreach ($providers as $provider)
            @php $connection = $connections->get($provider->value); @endphp
            <div class="rounded border border-gray-200 bg-white p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-medium">{{ $provider->label() }}</h2>
                        <p class="text-sm text-gray-500">
                            {{ $connection ? 'Previously connected' : 'Not yet connected' }}
                        </p>
                    </div>
                </div>

                <details class="mt-4 text-sm text-gray-600">
                    <summary class="cursor-pointer font-medium text-gray-800">What Savv will do</summary>
                    <p class="mt-2">
                        Savv will open a temporary remote browser for this import. You will log into
                        {{ $provider->label() }} directly in that browser. Savv does not intentionally
                        store your marketplace password or OTP. The temporary browser and its profile
                        will be deleted when the import ends. This demonstration is not an official
                        Amazon or Flipkart integration.
                    </p>
                </details>

                <form method="POST" action="{{ route('connections.start', $provider->value) }}" class="mt-4">
                    @csrf
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="accept_consent" value="1" required class="mt-1">
                        <span>I understand and want to import my {{ $provider->label() }} order information.</span>
                    </label>
                    <button type="submit" @disabled($activeImportSession)
                            class="mt-3 rounded bg-gray-900 px-4 py-2 text-sm text-white disabled:opacity-40">
                        Start temporary browser
                    </button>
                </form>
            </div>
        @endforeach
    </div>
</x-layout>
