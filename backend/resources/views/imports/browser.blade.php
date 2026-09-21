<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Temporary browser</title>
    @if ($streamEnabled)
        @vite(['resources/js/rbi-viewer.js'])
    @endif
    <style>
        :root { color-scheme: dark; }
        body {
            margin: 0;
            font-family: Heebo, ui-sans-serif, system-ui, sans-serif;
            background: #1b1b1b;
            color: #eeeeee;
        }
        .msg {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 2.5rem;
            text-align: center;
        }
        .msg p { margin: 0; max-width: 34rem; font-size: 14px; line-height: 1.65; color: #c9c9c9; }
        .dot {
            display: inline-block;
            height: 8px; width: 8px;
            border-radius: 999px;
            background: #FE7B49;
            margin-right: 6px;
        }
        .status {
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 700;
            color: #8D8D8D;
        }
        [data-rbi-canvas] { width: 100%; height: 100vh; position: relative; }
        [data-rbi-status] {
            position: absolute;
            inset: 0;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #1b1b1b;
        }
        [data-rbi-status] img { width: 28px; height: 28px; animation: rbi-spin 1s linear infinite; }
        @keyframes rbi-spin { to { transform: rotate(360deg); } }
        [data-rbi-status][hidden] { display: none !important; }
    </style>
</head>
<body>
    @if (! $streamEnabled)
        <div class="msg">
            <p>
                <span class="dot"></span>
                Local development mode - a Chromium window has opened directly on this machine's screen.
                Switch to it, log in, then come back here and press &ldquo;scan my orders&rdquo;.
            </p>
            <p class="status">Session {{ $importSession->status->value }}</p>
        </div>
    @elseif ($viewToken)
        <div data-rbi-viewer
             data-ws-url="{{ (str_starts_with(config('app.url'), 'https') ? 'wss://' : 'ws://') . request()->getHost() . '/rbi/' . $importSession->public_id . '?token=' . urlencode($viewToken) }}">
            <div data-rbi-canvas>
                <div data-rbi-status>
                    <img src="{{ asset('images/design/spinner.png') }}" alt="">
                    <p data-rbi-status-text class="status">Connecting</p>
                </div>
            </div>
        </div>
    @else
        <div class="msg">
            <p>The temporary browser is not ready to view right now.</p>
            <p class="status">Session {{ $importSession->status->value }}</p>
        </div>
    @endif
</body>
</html>
