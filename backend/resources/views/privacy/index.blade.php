<x-layout title="Privacy &amp; Data - Savv MVP">
    <h1 class="mb-6 text-xl font-semibold">Privacy &amp; Data</h1>

    <div class="mb-8 rounded border border-gray-200 bg-white p-5">
        <h2 class="mb-2 font-medium">Your data</h2>
        <p class="mb-4 text-sm text-gray-500">
            Retention period: {{ $retentionDays }} days. Export a copy of everything Savv MVP
            holds about you, or permanently delete your account and all associated data.
        </p>
        <div class="flex gap-3">
            <a href="{{ route('account.export') }}" class="rounded border border-gray-300 px-4 py-2 text-sm">Export my data</a>
            <button type="button" onclick="document.getElementById('delete-account').showModal()"
                    class="rounded border border-red-300 px-4 py-2 text-sm text-red-700">Delete my account</button>
        </div>
    </div>

    <dialog id="delete-account" class="rounded border border-gray-200 p-6">
        <form method="POST" action="{{ route('account.destroy') }}" class="space-y-4">
            @csrf @method('DELETE')
            <p class="text-sm">This permanently deletes your account and all imported orders. Enter your password to confirm.</p>
            <input type="password" name="password" required placeholder="Password" class="w-full rounded border-gray-300">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('delete-account').close()"
                        class="rounded border border-gray-300 px-3 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded bg-red-700 px-3 py-2 text-sm text-white">Delete permanently</button>
            </div>
        </form>
    </dialog>

    <h2 class="mb-2 mt-8 font-medium">Consent history</h2>
    <div class="divide-y divide-gray-200 rounded border border-gray-200 bg-white text-sm">
        @forelse ($consents as $consent)
            <div class="px-4 py-3">
                {{ ucfirst(str_replace('_', ' ', $consent->provider->value)) }}
                - accepted {{ $consent->accepted_at->toDayDateTimeString() }}
                (version {{ $consent->consent_version }})
                @if ($consent->revoked_at) - revoked {{ $consent->revoked_at->toDayDateTimeString() }} @endif
            </div>
        @empty
            <div class="px-4 py-3 text-gray-500">No consent recorded yet.</div>
        @endforelse
    </div>

    <h2 class="mb-2 mt-8 font-medium">Connections</h2>
    <div class="divide-y divide-gray-200 rounded border border-gray-200 bg-white text-sm">
        @forelse ($connections as $connection)
            <div class="px-4 py-3">
                {{ ucfirst(str_replace('_', ' ', $connection->provider->value)) }} - {{ $connection->status->value }}
            </div>
        @empty
            <div class="px-4 py-3 text-gray-500">No connections yet.</div>
        @endforelse
    </div>
</x-layout>
