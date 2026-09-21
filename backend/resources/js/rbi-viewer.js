import RFB from '@novnc/novnc';

/**
 * Connects the noVNC canvas to the same-origin websocket proxy Nginx exposes
 * for one temporary browser-isolation session. The websocket URL carries a
 * single-use, short-lived view token (see ImportSession::issueViewToken) -
 * never a persistent credential. Only used when RUNNER_STREAM_ENABLED=true
 * (the Ubuntu demo deployment); local Windows/dev shows the headed Chromium
 * window on the developer's own screen instead and never loads this file's
 * connection logic.
 */
// Observed in the wild: this init path can run more than once for a single
// page load (e.g. a retried/duplicated navigation at the network layer).
// A second `new RFB(...)` call racing the first against the same target
// element corrupts canvas sizing and leaves mouse/keyboard input dead, so
// only the first call is ever allowed to actually connect.
let started = false;

function initViewer() {
    if (started) return;

    const el = document.querySelector('[data-rbi-viewer]');
    if (!el) return;

    started = true;

    const wsUrl = el.dataset.wsUrl;
    const target = document.querySelector('[data-rbi-canvas]');
    const statusEl = document.querySelector('[data-rbi-status]');
    const statusTextEl = document.querySelector('[data-rbi-status-text]');

    const setStatus = (text) => {
        if (statusTextEl) statusTextEl.textContent = text;
        if (statusEl) statusEl.hidden = false;
    };

    // The very first connection attempt over this network path (WSL2 +
    // tunnel) intermittently drops mid-handshake (observed as the browser
    // aborting the request outright) before ever reaching the runner. The
    // view token stays valid for its full TTL and isn't single-use at the
    // transport level (see ImportSession::issueViewToken/registerViewToken),
    // so retry with the same token a few times from right here instead of
    // silently showing a dead canvas and hoping something upstream retries.
    const MAX_ATTEMPTS = 6;
    const RETRY_DELAY_MS = 1500;
    let attempt = 0;
    let everConnected = false;

    function connect() {
        attempt++;
        setStatus(attempt === 1 ? 'Connecting...' : `Reconnecting... (attempt ${attempt}/${MAX_ATTEMPTS})`);

        // Each attempt after the first needs a clean target - noVNC inserts
        // its own canvas into it and won't do that twice into the same node.
        if (attempt > 1) target.replaceChildren(statusEl ?? '');

        let rfb;
        try {
            rfb = new RFB(target, wsUrl, { credentials: {} });
        } catch (e) {
            console.error('rbi-viewer: RFB construction failed', e);
            setStatus('Could not start the remote browser viewer.');
            return;
        }

        rfb.viewOnly = false;
        rfb.scaleViewport = true;

        rfb.addEventListener('connect', () => {
            everConnected = true;
            if (statusEl) statusEl.hidden = true;
        });

        rfb.addEventListener('disconnect', (ev) => {
            console.warn('rbi-viewer: disconnected', ev.detail);

            if (everConnected) {
                setStatus('Viewer disconnected.');
                return;
            }

            if (attempt < MAX_ATTEMPTS) {
                setTimeout(connect, RETRY_DELAY_MS);
            } else {
                setStatus('Could not connect to the remote browser. Try refreshing the page.');
            }
        });
    }

    connect();
}

// This script is a deferred `type="module"` load, so it can run after
// DOMContentLoaded has already fired (in which case that event never comes
// again). Run immediately when the document is already past "loading"
// instead of only ever waiting for an event that may already be gone.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initViewer);
} else {
    initViewer();
}
