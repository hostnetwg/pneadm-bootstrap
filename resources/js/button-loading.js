/**
 * Stan ładowania na przyciskach (spinner Bootstrap) podczas długich POST/AJAX.
 * Formularze: data-loading-submit (+ opcjonalnie data-loading-text na buttonie).
 */
export function setButtonLoading(button, isLoading, loadingText = null) {
    if (!button) {
        return;
    }

    if (isLoading) {
        if (button.dataset.loadingOriginalHtml === undefined) {
            button.dataset.loadingOriginalHtml = button.innerHTML;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        const label = loadingText
            || button.getAttribute('data-loading-text')
            || 'Proszę czekać…';
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>'
            + escapeHtml(label);
        return;
    }

    button.disabled = false;
    button.removeAttribute('aria-busy');
    if (button.dataset.loadingOriginalHtml !== undefined) {
        button.innerHTML = button.dataset.loadingOriginalHtml;
        delete button.dataset.loadingOriginalHtml;
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Po submit formularza z data-loading-submit — spinner na klikniętym przycisku.
 * Pomija submit anulowany przez preventDefault (np. modal potwierdzenia).
 */
export function initLoadingSubmitForms(root = document) {
    root.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!form.hasAttribute('data-loading-submit')) {
            return;
        }

        // Inne handlery (modale) mogą wywołać preventDefault w tej samej turze.
        queueMicrotask(() => {
            if (event.defaultPrevented) {
                return;
            }

            let button = event.submitter instanceof HTMLElement ? event.submitter : null;
            if (!button || (button.getAttribute('type') || '').toLowerCase() === 'button') {
                button = form.querySelector('button[type="submit"], input[type="submit"]');
            }
            if (!button || button.getAttribute('aria-busy') === 'true') {
                return;
            }

            const text = button.getAttribute('data-loading-text')
                || form.getAttribute('data-loading-text')
                || 'Proszę czekać…';
            setButtonLoading(button, true, text);
        });
    }, true);
}
