<div class="modal fade" id="platformMigrationEmailPreviewModal" tabindex="-1" aria-labelledby="platformMigrationEmailPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="platformMigrationEmailPreviewModalLabel">
                    <i class="bi bi-envelope me-1"></i>Podgląd e-maila
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <div id="platformMigrationEmailPreviewLoading" class="text-muted small py-3 text-center">
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Ładowanie podglądu wiadomości…
                </div>
                <div id="platformMigrationEmailPreviewError" class="alert alert-danger d-none" role="alert"></div>
                <div id="platformMigrationEmailPreviewContent" class="d-none">
                    <p class="small text-muted mb-2" id="platformMigrationEmailPreviewVariant"></p>
                    <div id="platformMigrationEmailPreviewExpired" class="alert alert-warning d-none" role="alert">
                        Uwaga: dostęp tej osoby już wygasł. Mail i tak może zostać wysłany.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1" for="platformMigrationEmailPreviewTo">Do</label>
                        <input type="text" class="form-control form-control-sm" id="platformMigrationEmailPreviewTo" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1" for="platformMigrationEmailPreviewSubject">Temat</label>
                        <input type="text" class="form-control form-control-sm" id="platformMigrationEmailPreviewSubject" readonly>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold mb-1" for="platformMigrationEmailPreviewFrame">Treść (podgląd HTML)</label>
                        <iframe id="platformMigrationEmailPreviewFrame"
                                title="Podgląd wiadomości e-mail"
                                class="w-100 border rounded bg-white"
                                style="height: 28rem;"></iframe>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                <form id="platformMigrationEmailPreviewSendForm" method="POST" class="d-inline" data-loading-submit>
                    @csrf
                    <button type="submit" class="btn btn-success" id="platformMigrationEmailPreviewSendBtn" disabled data-loading-text="Wysyłanie…">
                        <i class="bi bi-send me-1"></i>Wyślij e-mail
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@once
    @push('scripts')
    <script>
        (function () {
            var modalEl = document.getElementById('platformMigrationEmailPreviewModal');
            if (!modalEl) {
                return;
            }
            var loadingEl = document.getElementById('platformMigrationEmailPreviewLoading');
            var errorEl = document.getElementById('platformMigrationEmailPreviewError');
            var contentEl = document.getElementById('platformMigrationEmailPreviewContent');
            var variantEl = document.getElementById('platformMigrationEmailPreviewVariant');
            var expiredEl = document.getElementById('platformMigrationEmailPreviewExpired');
            var toEl = document.getElementById('platformMigrationEmailPreviewTo');
            var subjectEl = document.getElementById('platformMigrationEmailPreviewSubject');
            var frameEl = document.getElementById('platformMigrationEmailPreviewFrame');
            var formEl = document.getElementById('platformMigrationEmailPreviewSendForm');
            var sendBtn = document.getElementById('platformMigrationEmailPreviewSendBtn');

            function resetUi() {
                loadingEl.classList.remove('d-none');
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
                contentEl.classList.add('d-none');
                expiredEl.classList.add('d-none');
                variantEl.textContent = '';
                toEl.value = '';
                subjectEl.value = '';
                if (window.PneSetEmailPreviewFrame) {
                    window.PneSetEmailPreviewFrame(frameEl, '');
                }
                sendBtn.disabled = true;
                formEl.setAttribute('action', '');
            }

            modalEl.addEventListener('show.bs.modal', async function (event) {
                var trigger = event.relatedTarget;
                if (!trigger) {
                    return;
                }
                resetUi();
                var previewUrl = trigger.getAttribute('data-preview-url');
                var sendUrl = trigger.getAttribute('data-send-url');
                formEl.setAttribute('action', sendUrl || '');
                if (!previewUrl) {
                    loadingEl.classList.add('d-none');
                    errorEl.textContent = 'Brak adresu podglądu wiadomości.';
                    errorEl.classList.remove('d-none');
                    return;
                }

                var controller = new AbortController();
                var timeoutId = setTimeout(function () { controller.abort(); }, 15000);
                try {
                    var res = await fetch(previewUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: controller.signal,
                        credentials: 'same-origin'
                    });
                    clearTimeout(timeoutId);
                    var data = await res.json().catch(function () { return {}; });
                    loadingEl.classList.add('d-none');
                    if (!res.ok || !data.success) {
                        errorEl.textContent = data.error || ('Nie udało się pobrać podglądu (HTTP ' + res.status + ').');
                        errorEl.classList.remove('d-none');
                        return;
                    }
                    toEl.value = data.to || '';
                    subjectEl.value = data.subject || '';
                    variantEl.textContent = data.variant_label || '';
                    if (data.access_expired) {
                        expiredEl.classList.remove('d-none');
                    }
                    if (window.PneSetEmailPreviewFrame) {
                        window.PneSetEmailPreviewFrame(frameEl, data.body_html || '');
                    }
                    contentEl.classList.remove('d-none');
                    sendBtn.disabled = false;
                } catch (e) {
                    clearTimeout(timeoutId);
                    loadingEl.classList.add('d-none');
                    errorEl.textContent = (e && e.name === 'AbortError')
                        ? 'Podgląd nie odpowiedział w 15 s. Odśwież stronę i spróbuj ponownie.'
                        : 'Nie udało się pobrać podglądu wiadomości.';
                    errorEl.classList.remove('d-none');
                }
            });
        })();
    </script>
    @endpush
@endonce
