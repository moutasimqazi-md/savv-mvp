/**
 * Minimal vanilla-JS driver for the import session page. No framework -
 * just fetch() polling and DOM updates. Lives entirely client-side; the
 * temporary browser itself is never rendered into this page except as an
 * embedded noVNC viewer iframe/websocket (see imports/browser.blade.php).
 */
function initImportSessionPage() {
    const root = document.querySelector('[data-import-session]');
    if (!root) return;

    const statusUrl = root.dataset.statusUrl;
    const previewUrl = root.dataset.previewUrl;
    const statusEl = document.querySelector('[data-session-status]');
    const previewList = document.querySelector('[data-preview-list]');
    const confirmForm = document.querySelector('[data-confirm-form]');

    let polling = true;

    async function poll() {
        if (!polling) return;

        try {
            const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            const data = await res.json();

            if (statusEl) statusEl.textContent = data.status;

            if (['preview_ready', 'importing', 'completed', 'cancelled', 'expired', 'failed', 'terminated'].includes(data.status)) {
                if (data.status === 'preview_ready') {
                    await loadPreview();
                }

                if (['completed', 'cancelled', 'expired', 'failed', 'terminated'].includes(data.status)) {
                    polling = false;
                    return;
                }
            }
        } catch (e) {
            // Network hiccup - keep polling.
        }

        setTimeout(poll, 2000);
    }

    async function loadPreview() {
        if (!previewList) return;

        const res = await fetch(previewUrl, { headers: { Accept: 'application/json' } });
        const data = await res.json();

        previewList.innerHTML = '';

        data.previews.forEach((preview) => {
            const row = document.createElement('label');
            row.className = 'flex items-start gap-3 border-b border-gray-200 py-3';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'selected[]';
            checkbox.value = preview.public_id;
            checkbox.checked = preview.selected;
            checkbox.className = 'mt-1';

            const details = document.createElement('div');
            const total = preview.order.total ? `₹${(preview.order.total / 100).toFixed(2)}` : 'amount unknown';
            details.innerHTML = `
                <div class="font-medium">${escapeHtml(preview.provider_order_id)} - ${escapeHtml(total)}</div>
                <div class="text-sm text-gray-500">${escapeHtml(preview.order.normalized_status || 'unknown')} - ${(preview.order.items || []).length} item(s)</div>
                ${preview.warnings && preview.warnings.length ? `<div class="text-sm text-amber-600">${preview.warnings.map(escapeHtml).join('<br>')}</div>` : ''}
            `;

            row.appendChild(checkbox);
            row.appendChild(details);
            previewList.appendChild(row);
        });

        if (confirmForm) confirmForm.hidden = data.previews.length === 0;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }

    poll();
}

document.addEventListener('DOMContentLoaded', initImportSessionPage);
