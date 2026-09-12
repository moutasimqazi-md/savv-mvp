import RFB from '@novnc/novnc/core/rfb.js';

/**
 * Connects the noVNC canvas to the same-origin websocket proxy Nginx exposes
 * for one temporary browser-isolation session. The websocket URL carries a
 * single-use, short-lived view token (see ImportSession::issueViewToken) -
 * never a persistent credential. Only used when RUNNER_STREAM_ENABLED=true
 * (the Ubuntu demo deployment); local Windows/dev shows the headed Chromium
 * window on the developer's own screen instead and never loads this file's
 * connection logic.
 */
function initViewer() {
    const el = document.querySelector('[data-rbi-viewer]');
    if (!el) return;

    const wsUrl = el.dataset.wsUrl;
    const target = document.querySelector('[data-rbi-canvas]');

    let rfb;
    try {
        rfb = new RFB(target, wsUrl, { credentials: {} });
    } catch (e) {
        target.textContent = 'Could not start the remote browser viewer.';
        return;
    }

    rfb.viewOnly = false;
    rfb.scaleViewport = true;

    rfb.addEventListener('disconnect', () => {
        target.insertAdjacentHTML('beforeend', '<p class="text-sm text-gray-500 p-4">Viewer disconnected.</p>');
    });
}

document.addEventListener('DOMContentLoaded', initViewer);
