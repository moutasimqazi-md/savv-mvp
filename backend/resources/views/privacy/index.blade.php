<x-layout title="Privacy &amp; Data - Savv">

    <x-ui.page-header title="Privacy &amp; Data" subtitle="What Savv holds about you, and how to take it back." />

    <div class="grid gap-5 lg:grid-cols-3">

        <div class="space-y-5 lg:col-span-2">

            {{-- Your data --}}
            <x-ui.card>
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-savv-orange/10 text-savv-orange">
                        <x-ui.icon name="shield" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-bold">Your data</h2>
                        <p class="mt-1 text-sm leading-relaxed text-savv-gray">
                            Export a copy of everything Savv holds about you, or permanently delete your
                            account and all associated data.
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button :href="route('account.export')" variant="outline" size="sm">
                                <x-ui.icon name="download" class="h-4 w-4" />
                                Export my data
                            </x-ui.button>
                            <x-ui.button type="button" variant="danger" size="sm"
                                         onclick="document.getElementById('delete-account').showModal()">
                                <x-ui.icon name="trash" class="h-4 w-4" />
                                Delete my account
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            {{-- Consent history --}}
            <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                <div class="border-b border-savv-graylight px-5 py-4">
                    <h2 class="text-sm font-bold">Consent history</h2>
                </div>

                @forelse ($consents as $consent)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-savv-graylight px-5 py-3.5 last:border-0">
                        <div>
                            <div class="text-sm font-semibold">{{ $consent->provider->label() }}</div>
                            <div class="mt-0.5 text-xs text-savv-gray">
                                Accepted {{ $consent->accepted_at->format('d M Y, H:i') }}
                                &middot; version {{ $consent->consent_version }}
                            </div>
                        </div>
                        @if ($consent->revoked_at)
                            <x-ui.badge tone="danger" dot>Revoked {{ $consent->revoked_at->format('d M Y') }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="success" dot>Active</x-ui.badge>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-savv-gray">No consent recorded yet.</div>
                @endforelse
            </div>

            {{-- Connections --}}
            <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                <div class="border-b border-savv-graylight px-5 py-4">
                    <h2 class="text-sm font-bold">Connections</h2>
                </div>

                @forelse ($connections as $connection)
                    <div class="flex items-center justify-between gap-3 border-b border-savv-graylight px-5 py-3.5 last:border-0">
                        <x-ui.provider-chip :provider="$connection->provider" />
                        <x-ui.badge tone="neutral">{{ ucfirst(str_replace('_', ' ', $connection->status->value)) }}</x-ui.badge>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-savv-gray">No connections yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Side --}}
        <div class="space-y-5">
            <x-ui.card>
                <h2 class="text-xs font-bold uppercase tracking-wider text-savv-gray">Retention</h2>
                <p class="mt-3 text-3xl font-bold leading-none text-savv-orange">{{ $retentionDays }}</p>
                <p class="mt-1.5 text-sm text-savv-gray">days of imported history kept</p>
            </x-ui.card>

            <x-ui.card class="bg-savv-light">
                <h2 class="text-xs font-bold uppercase tracking-wider text-savv-gray">Never stored</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach (['Passwords', 'One-time codes (OTP)', 'Cookies & session tokens', 'Payment card numbers'] as $item)
                        <li class="flex items-center gap-2">
                            <img src="{{ asset('images/design/check-circle.png') }}" alt="" class="h-4 w-4 shrink-0">
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>

    <dialog id="delete-account" class="w-[min(28rem,92vw)] rounded-2xl border border-savv-graylight p-0 backdrop:bg-black/40">
        <form method="POST" action="{{ route('account.destroy') }}" class="p-6">
            @csrf @method('DELETE')

            <div class="flex items-start gap-3">
                <img src="{{ asset('images/design/error.png') }}" alt="" class="mt-0.5 h-6 w-6 shrink-0">
                <div>
                    <h2 class="text-base font-bold">Delete your account</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-savv-gray">
                        This permanently deletes your account and all imported orders, returns, refunds
                        and subscriptions. Enter your password to confirm.
                    </p>
                </div>
            </div>

            <input type="password" name="password" required placeholder="Password"
                   class="mt-5 w-full rounded-xl border-savv-graylight text-sm focus:border-savv-error focus:ring-savv-error">

            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="button" variant="outline" size="sm"
                             onclick="document.getElementById('delete-account').close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="danger-solid" size="sm">Delete permanently</x-ui.button>
            </div>
        </form>
    </dialog>

</x-layout>
