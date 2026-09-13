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
    const errorEl = document.querySelector('[data-session-error]');
    const errorTextEl = document.querySelector('[data-session-error-text]');
    const spinnerEl = document.querySelector('[data-session-spinner]');
    const previewList = document.querySelector('[data-preview-list]');
    const previewEmpty = document.querySelector('[data-preview-empty]');
    const confirmForm = document.querySelector('[data-confirm-form]');

    // Keep in sync with the safe_error_code values set in
    // app/Jobs/ScanImportSession.php, app/Services/ImportSessionService.php,
    // and app/Jobs/ConfirmImportSession.php.
    const ERROR_MESSAGES = {
        unsupported_layout: "Savv Companion could not safely read this page version. No account credentials or page contents were uploaded.",
        unsupported_host: 'This page is not on the official marketplace site, so scanning was stopped.',
        runner_unreachable: 'Could not start the temporary browser. Please try again shortly.',
        scan_failed: 'The scan could not be completed. Please try again.',
        import_failed: 'The import could not be completed. Please try again.',
        runner_process_lost: 'The temporary browser was lost unexpectedly. Please start a new import.',
    };

    let polling = true;

    async function poll() {
        if (!polling) return;

        try {
            const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            const data = await res.json();

            if (statusEl) statusEl.textContent = data.status;

            if (errorEl) {
                if (data.safe_error_code) {
                    if (errorTextEl) errorTextEl.textContent = ERROR_MESSAGES[data.safe_error_code] ?? 'Something went wrong with this import.';
                    errorEl.hidden = false;
                } else {
                    errorEl.hidden = true;
                }
            }

            const terminalStatuses = ['completed', 'cancelled', 'expired', 'failed', 'terminated'];

            if (spinnerEl) spinnerEl.hidden = terminalStatuses.includes(data.status);

            if (['preview_ready', 'importing', 'completed', 'cancelled', 'expired', 'failed', 'terminated'].includes(data.status)) {
                if (data.status === 'preview_ready') {
                    await loadPreview();
                }

                if (terminalStatuses.includes(data.status)) {
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
            row.className = 'flex cursor-pointer items-start gap-3 border-b border-savv-graylight py-3 last:border-0';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'selected[]';
            checkbox.value = preview.public_id;
            checkbox.checked = preview.selected;
            checkbox.className = 'mt-1 rounded border-savv-graylight text-savv-orange focus:ring-savv-orange';

            const details = document.createElement('div');

            if (data.kind === 'subscription') {
                const s = preview.subscription;
                const price = s.price ? `${s.currency} ${(s.price / 100).toFixed(2)} / ${s.billing_cycle}` : 'price unknown';
                details.innerHTML = `
                    <div class="font-medium">${escapeHtml(s.plan_name)} - ${escapeHtml(price)}</div>
                    <div class="text-sm text-savv-gray">${escapeHtml(s.normalized_status || 'unknown')}${s.renewal_at ? ' - renews ' + escapeHtml(s.renewal_at.slice(0, 10)) : ''}</div>
                    ${preview.warnings && preview.warnings.length ? `<div class="text-sm text-savv-orange">${preview.warnings.map(escapeHtml).join('<br>')}</div>` : ''}
                `;
            } else {
                const total = preview.order.total ? `₹${(preview.order.total / 100).toFixed(2)}` : 'amount unknown';
                details.innerHTML = `
                    <div class="font-medium">${escapeHtml(preview.provider_order_id)} - ${escapeHtml(total)}</div>
                    <div class="text-sm text-savv-gray">${escapeHtml(preview.order.normalized_status || 'unknown')} - ${(preview.order.items || []).length} item(s)</div>
                    ${preview.warnings && preview.warnings.length ? `<div class="text-sm text-savv-orange">${preview.warnings.map(escapeHtml).join('<br>')}</div>` : ''}
                `;
            }

            row.appendChild(checkbox);
            row.appendChild(details);
            previewList.appendChild(row);
        });

        if (confirmForm) confirmForm.hidden = data.previews.length === 0;
        if (previewEmpty) previewEmpty.hidden = data.previews.length > 0;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }

    poll();
}

document.addEventListener('DOMContentLoaded', initImportSessionPage);
