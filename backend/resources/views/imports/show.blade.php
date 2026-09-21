@php
    use Savv\Enums\ImportSessionStatus;

    $status = $importSession->status;
    $isSubscription = $importSession->provider->kind()->value === 'subscription';
    $noun = $isSubscription ? 'subscription' : 'orders';

    $importStep = match ($status) {
        ImportSessionStatus::Requested, ImportSessionStatus::Starting => 0,
        ImportSessionStatus::Ready, ImportSessionStatus::AwaitingLogin, ImportSessionStatus::ReadyToScan => 1,
        ImportSessionStatus::Scanning => 2,
        ImportSessionStatus::PreviewReady, ImportSessionStatus::Importing => 3,
        ImportSessionStatus::Completed => 4,
        default => 1,
    };
@endphp

<x-layout title="Import - Savv">

    <div data-import-session
         data-status-url="{{ route('imports.show', $importSession) }}"
         data-preview-url="{{ route('imports.preview', $importSession) }}"
         data-noun="{{ $noun }}">

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <x-ui.provider-chip :provider="$importSession->provider" />
                <h1 class="mt-3 text-2xl font-bold tracking-tight">
                    Importing from {{ $importSession->provider->label() }}
                </h1>
                <p class="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-savv-gray">
                    <img data-session-spinner src="{{ asset('images/design/spinner.png') }}" alt=""
                         class="h-4 w-4 animate-spin" @if ($status->isTerminal()) hidden @endif>
                    <span>Status</span>
                    <span data-session-status class="font-semibold text-savv-darkgray">{{ $status->value }}</span>
                    <span class="text-savv-graylight">|</span>
                    <span>expires {{ $importSession->expires_at->diffForHumans() }}</span>
                </p>
            </div>

            <form data-cancel-form method="POST" action="{{ route('imports.cancel', $importSession) }}"
                  @if ($status->isTerminal()) hidden @endif
                  onsubmit="return confirm('Cancel this import and close the temporary browser?');">
                @csrf
                <x-ui.button type="submit" variant="danger" size="sm">
                    <x-ui.icon name="x" class="h-4 w-4" />
                    Exit &amp; cancel session
                </x-ui.button>
            </form>
        </div>

        {{-- Progress --}}
        <x-ui.card class="mt-6" padding="px-5 py-6">
            <x-ui.stepper :steps="['Browser', 'Log in', 'Scan', 'Review', 'Done']" :current="$importStep" />
        </x-ui.card>

        <div data-session-error @if (! $importSession->safe_error_code) hidden @endif
             class="mt-5 flex items-start gap-2.5 rounded-xl border border-savv-error/30 bg-savv-error/5 px-4 py-3 text-sm text-savv-error">
            <img src="{{ asset('images/design/error.png') }}" alt="" class="mt-0.5 h-5 w-5 shrink-0">
            <span data-session-error-text class="font-medium">{{ $importSession->safe_error_code }}</span>
        </div>

        @php
            $isDone = $status === ImportSessionStatus::Completed;
            // Once scanning starts, the temporary browser has already done its
            // job (login) and the runner closes it - it is not an error, but
            // showing the live viewer (or its "not ready" fallback) at that
            // point reads as broken. Swap to a plain progress message instead.
            $browserPhaseOver = in_array($status, [
                ImportSessionStatus::Scanning,
                ImportSessionStatus::PreviewReady,
                ImportSessionStatus::Importing,
                ImportSessionStatus::Completed,
            ], true);
            $processingText = match ($status) {
                ImportSessionStatus::Scanning => "Scanning your {$noun}...",
                ImportSessionStatus::PreviewReady => 'Scan complete - review the results on the right.',
                ImportSessionStatus::Importing => "Saving your {$noun}...",
                default => 'Finishing up...',
            };
        @endphp

        <div data-session-complete @unless ($isDone) hidden @endunless
             class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-savv-green/30 bg-savv-green/10 px-4 py-3 text-sm text-green-800">
            <div class="flex items-center gap-2.5">
                <x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 text-savv-green" />
                <span class="font-medium">Import complete &mdash; your {{ $noun }} {{ $isSubscription ? 'was' : 'were' }} saved.</span>
            </div>
            <div class="flex gap-2">
                <x-ui.button :href="$isSubscription ? route('subscriptions.index') : route('orders.index')" variant="primary" size="sm">
                    View {{ $noun }}
                </x-ui.button>
                <x-ui.button :href="route('connections.index')" variant="outline" size="sm">Start another import</x-ui.button>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3" data-import-body @if ($isDone) hidden @endif>

            {{-- Browser --}}
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                    <div class="flex items-center gap-2 border-b border-savv-graylight bg-savv-light px-4 py-2.5">
                        <div class="flex gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-savv-error/60"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-savv-orange/60"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-savv-green/60"></span>
                        </div>
                        <span class="ml-2 truncate text-xs font-medium text-savv-gray">
                            Temporary isolated browser &middot; deleted when this import ends
                        </span>
                    </div>
                    <iframe data-browser-live @if ($browserPhaseOver) hidden @endif
                            src="{{ route('imports.browser', $importSession) }}" class="h-[480px] w-full" title="Temporary browser"></iframe>

                    <div data-browser-processing @unless ($browserPhaseOver) hidden @endunless
                         class="flex h-[480px] flex-col items-center justify-center gap-3 bg-[#1b1b1b] px-8 text-center">
                        <img src="{{ asset('images/design/spinner.png') }}" alt="" class="h-8 w-8 animate-spin">
                        <p data-browser-processing-text class="text-sm font-medium text-white">{{ $processingText }}</p>
                        <p class="text-xs text-[#8D8D8D]">The temporary browser has already closed - this part happens on our side.</p>
                    </div>
                </div>

                <form data-scan-form method="POST" action="{{ route('imports.scan', $importSession) }}"
                      class="mt-4" @if ($browserPhaseOver) hidden @endif>
                    @csrf
                    <x-ui.button type="submit" variant="accent">
                        I'm logged in - scan my {{ $noun }}
                    </x-ui.button>
                </form>
            </div>

            {{-- Preview --}}
            <div class="space-y-5">
                <div class="overflow-hidden rounded-2xl border border-savv-graylight bg-white">
                    <div class="border-b border-savv-graylight px-5 py-4">
                        <h2 class="text-sm font-bold">Preview</h2>
                        <p class="mt-0.5 text-xs text-savv-gray">Nothing is saved until you confirm.</p>
                    </div>

                    <form data-confirm-form method="POST" action="{{ route('imports.confirm', $importSession) }}" hidden>
                        @csrf
                        <div data-preview-list class="max-h-[360px] overflow-y-auto px-5"></div>
                        <div class="border-t border-savv-graylight p-4">
                            <x-ui.button type="submit" size="sm" class="w-full">
                                Import selected {{ $noun }}
                            </x-ui.button>
                        </div>
                    </form>

                    <div data-preview-empty class="px-5 py-10 text-center">
                        <img src="{{ asset('images/design/illustration-tracking.png') }}" alt="" class="mx-auto h-12 w-12">
                        <p class="mt-3 text-xs leading-relaxed text-savv-gray">
                            Nothing to preview yet. Log in on the left, then press
                            &ldquo;scan my {{ $noun }}&rdquo;.
                        </p>
                    </div>
                </div>

                <x-ui.card class="bg-savv-light">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-savv-gray">Your safety</h2>
                    <ul class="mt-3 space-y-2 text-xs leading-relaxed">
                        @foreach ([
                            'Your password and OTP go only to the site itself.',
                            'Savv never stores cookies or session tokens.',
                            'The browser profile is destroyed when this ends.',
                        ] as $point)
                            <li class="flex gap-2">
                                <img src="{{ asset('images/design/check-circle.png') }}" alt="" class="mt-0.5 h-3.5 w-3.5 shrink-0">
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            </div>
        </div>
    </div>

</x-layout>
