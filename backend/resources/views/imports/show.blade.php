<x-layout title="Import - Savv MVP">
    <div data-import-session
         data-status-url="{{ route('imports.show', $importSession) }}"
         data-preview-url="{{ route('imports.preview', $importSession) }}">

        <h1 class="mb-2 text-xl font-semibold">
            Importing from {{ $importSession->provider->label() }}
        </h1>
        <p class="mb-3 text-sm text-gray-500">
            Status: <span data-session-status class="font-medium">{{ $importSession->status->value }}</span>
            - expires {{ $importSession->expires_at->diffForHumans() }}
        </p>

        <div data-session-error @if (! $importSession->safe_error_code) hidden @endif
             class="mb-6 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
            {{ $importSession->safe_error_code }}
        </div>

        <div class="mb-6 overflow-hidden rounded border border-gray-200 bg-white">
            <iframe src="{{ route('imports.browser', $importSession) }}" class="h-[480px] w-full" title="Temporary browser"></iframe>
        </div>

        <div class="mb-8 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('imports.scan', $importSession) }}">
                @csrf
                <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm text-white">
                    I'm logged in - scan my orders
                </button>
            </form>
            <form method="POST" action="{{ route('imports.cancel', $importSession) }}">
                @csrf
                <button type="submit" class="rounded border border-red-300 px-4 py-2 text-sm text-red-700">
                    Cancel import
                </button>
            </form>
        </div>

        <h2 class="mb-3 text-lg font-semibold">Preview</h2>
        <form data-confirm-form method="POST" action="{{ route('imports.confirm', $importSession) }}" hidden>
            @csrf
            <div data-preview-list class="mb-4 rounded border border-gray-200 bg-white px-4"></div>
            <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm text-white">Import selected orders</button>
        </form>
        <p class="text-sm text-gray-500" data-preview-empty>
            Nothing to preview yet. Log in above and press "I'm logged in - scan my orders".
        </p>
    </div>
</x-layout>
