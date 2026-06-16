<div class="modal fade" id="campaignLinksModal" tabindex="-1" aria-labelledby="campaignLinksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="campaignLinksModalLabel">
                    Linki kampanii <code id="campaignLinksModalCode"></code>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body" id="campaignLinksModalBody">
                <p class="text-muted mb-0">Ładowanie…</p>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="{{ route('marketing.help.links') }}" class="btn btn-sm btn-link text-decoration-none" target="_blank">
                    <i class="bi bi-book"></i> Pomoc: linki i UTM
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('campaignLinksModal');
    if (!modalEl) {
        return;
    }

    const codeEl = document.getElementById('campaignLinksModalCode');
    const bodyEl = document.getElementById('campaignLinksModalBody');
    const helpLinksUrl = @json(route('marketing.help.links'));

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function bindCopyButtons(root) {
        root.querySelectorAll('.btn-copy-campaign-url').forEach(function (copyBtn) {
            copyBtn.dataset.copyBound = '';
            copyBtn.addEventListener('click', function () {
                const selector = copyBtn.getAttribute('data-copy-target');
                const input = selector ? document.querySelector(selector) : null;
                if (!input || !input.value) {
                    return;
                }
                navigator.clipboard.writeText(input.value).then(function () {
                    const original = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="bi bi-check2"></i>';
                    copyBtn.classList.add('btn-success');
                    copyBtn.classList.remove('btn-primary', 'btn-outline-secondary', 'btn-success', 'btn-outline-success');
                    setTimeout(function () {
                        copyBtn.innerHTML = original;
                        copyBtn.classList.remove('btn-success');
                        const target = copyBtn.getAttribute('data-copy-target') || '';
                        if (target.includes('Legacy')) {
                            copyBtn.classList.add('btn-outline-secondary');
                        } else if (target.includes('Short')) {
                            copyBtn.classList.add('btn-success');
                        } else {
                            copyBtn.classList.add('btn-primary');
                        }
                    }, 1500);
                });
            });
        });
    }

    function bindVerifyButton(root) {
        const verifyBtn = root.querySelector('.btn-verify-short-link');
        if (!verifyBtn || verifyBtn.dataset.verifyBound === '1') {
            return;
        }
        verifyBtn.dataset.verifyBound = '1';
        verifyBtn.addEventListener('click', function () {
            const verifyUrl = verifyBtn.getAttribute('data-verify-url');
            const resultEl = root.querySelector('[data-short-link-test]');
            if (!verifyUrl || !resultEl) {
                return;
            }
            const original = verifyBtn.innerHTML;
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            fetch(verifyUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { data: data };
                    });
                })
                .then(function (payload) {
                    const data = payload.data || {};
                    const alertClass = data.ok ? 'alert-success' : 'alert-warning';
                    resultEl.innerHTML = '<div class="alert ' + alertClass + ' small py-2 mb-0"><strong>'
                        + (data.ok ? 'OK' : 'Uwaga') + ':</strong> ' + escapeHtml(data.message || '') + '</div>';
                    resultEl.style.display = 'block';
                })
                .catch(function () {
                    resultEl.innerHTML = '<div class="alert alert-danger small py-2 mb-0">Nie udało się wykonać testu.</div>';
                    resultEl.style.display = 'block';
                })
                .finally(function () {
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = original;
                });
        });
    }

    modalEl.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) {
            return;
        }

        const hasLinks = btn.getAttribute('data-has-links') === '1';
        const code = btn.getAttribute('data-campaign-code') || '';
        const prefix = 'modalCampaign' + (btn.getAttribute('data-campaign-id') || '');

        if (codeEl) {
            codeEl.textContent = code;
        }

        if (!hasLinks) {
            bodyEl.innerHTML = '<p class="text-muted mb-0">Brak powiązanego szkolenia — ustaw je w edycji kampanii, aby wygenerować linki.</p>';
            return;
        }

        const utmUrl = btn.getAttribute('data-utm-url') || '';
        const legacyUrl = btn.getAttribute('data-legacy-url') || '';
        const shortUrl = btn.getAttribute('data-short-url') || '';
        const verifyUrl = btn.getAttribute('data-verify-short-link-url') || '';
        const utmSource = btn.getAttribute('data-utm-source') || '';
        const utmMedium = btn.getAttribute('data-utm-medium') || '';
        const utmCampaign = btn.getAttribute('data-utm-campaign') || '';
        const utmContent = btn.getAttribute('data-utm-content') || '';

        bodyEl.innerHTML =
            '<div class="d-flex flex-wrap gap-2 mb-3">' +
                '<span class="badge bg-light text-dark border small">utm_source <code>' + escapeHtml(utmSource) + '</code></span>' +
                '<span class="badge bg-light text-dark border small">utm_medium <code>' + escapeHtml(utmMedium) + '</code></span>' +
                '<span class="badge bg-light text-dark border small">utm_campaign <code>' + escapeHtml(utmCampaign) + '</code></span>' +
                (utmContent
                    ? '<span class="badge bg-light text-dark border small">utm_content <code>' + escapeHtml(utmContent) + '</code></span>'
                    : '') +
            '</div>' +
            '<div class="mb-3">' +
                '<div class="small fw-semibold mb-1"><span class="badge bg-primary me-1">1</span> Link pełny UTM</div>' +
                '<div class="input-group input-group-sm">' +
                    '<input type="text" class="form-control font-monospace small" readonly id="' + prefix + 'UtmUrl" value="' + escapeHtml(utmUrl) + '">' +
                    '<button type="button" class="btn btn-primary btn-copy-campaign-url" data-copy-target="#' + prefix + 'UtmUrl"><i class="bi bi-clipboard"></i></button>' +
                '</div>' +
            '</div>' +
            '<div class="mb-3">' +
                '<div class="small fw-semibold mb-1"><span class="badge bg-success me-1">2</span> Link krótki (social media)</div>' +
                '<div class="input-group input-group-sm">' +
                    '<input type="text" class="form-control font-monospace small" readonly id="' + prefix + 'ShortUrl" value="' + escapeHtml(shortUrl) + '">' +
                    '<button type="button" class="btn btn-success btn-copy-campaign-url" data-copy-target="#' + prefix + 'ShortUrl"><i class="bi bi-clipboard"></i></button>' +
                '</div>' +
                '<div class="d-flex flex-wrap gap-2 mt-2">' +
                    (shortUrl ? '<a href="' + escapeHtml(shortUrl) + '" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> Otwórz</a>' : '') +
                    (verifyUrl ? '<button type="button" class="btn btn-sm btn-outline-info btn-verify-short-link" data-verify-url="' + escapeHtml(verifyUrl) + '"><i class="bi bi-shield-check"></i> Test</button>' : '') +
                '</div>' +
                '<div data-short-link-test class="mt-2" style="display:none"></div>' +
            '</div>' +
            '<div class="mb-0">' +
                '<div class="small fw-semibold mb-1"><span class="badge bg-secondary me-1">3</span> Link legacy</div>' +
                '<div class="input-group input-group-sm">' +
                    '<input type="text" class="form-control font-monospace small" readonly id="' + prefix + 'LegacyUrl" value="' + escapeHtml(legacyUrl) + '">' +
                    '<button type="button" class="btn btn-outline-secondary btn-copy-campaign-url" data-copy-target="#' + prefix + 'LegacyUrl"><i class="bi bi-clipboard"></i></button>' +
                '</div>' +
            '</div>';

        bindCopyButtons(bodyEl);
        bindVerifyButton(bodyEl);
    });
});
</script>
@endpush
