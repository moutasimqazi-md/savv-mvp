<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Temporary browser</title>
    @if ($streamEnabled)
        @vite(['resources/js/rbi-viewer.js'])
    @endif
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #111827; color: #e5e7eb; }
        .msg { padding: 2rem; text-align: center; }
        [data-rbi-canvas] { width: 100%; height: 100vh; }
    </style>
</head>
<body>
    @if (! $streamEnabled)
        <div class="msg">
            <p>Local development mode: a Chromium window has opened directly on this
               machine's screen. Switch to it, log in, then come back here and press
               "I'm logged in - scan my orders".</p>
            <p>Session status: {{ $importSession->status->value }}</p>
        </div>
    @elseif ($viewToken)
        <div data-rbi-viewer
             data-ws-url="{{ (str_starts_with(config('app.url'), 'https') ? 'wss://' : 'ws://') . request()->getHost() . '/rbi/' . $importSession->public_id . '?token=' . urlencode($viewToken) }}">
            <div data-rbi-canvas></div>
        </div>
    @else
        <div class="msg">
            <p>The temporary browser is not ready to view right now (status: {{ $importSession->status->value }}).</p>
        </div>
    @endif
</body>
</html>
