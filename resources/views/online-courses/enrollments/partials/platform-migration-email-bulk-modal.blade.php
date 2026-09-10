<div class="modal fade" id="bulkPlatformMigrationEmailModal" tabindex="-1" aria-labelledby="bulkPlatformMigrationEmailModalLabel" aria-hidden="true"
     data-preview-url="{{ route('online-courses.enrollments.preview-platform-migration-bulk', $online_course) }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bulkPlatformMigrationEmailModalLabel">
                    <i class="bi bi-send me-2"></i>Wyślij e-maile zbiorczo — podgląd
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    Wysyłka zbiorcza obejmuje wyłącznie osoby z <strong>ważnym dostępem</strong>.
                    Wysyłka idzie w tle — na produkcji musi działać kolejka (<code>queue:work</code>).
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Tryb wysyłki</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="bulk_platform_migration_mode" id="bulk_platform_migration_mode_unsent" value="unsent" checked>
                        <label class="form-check-label" for="bulk_platform_migration_mode_unsent">Wyślij tylko do tych, do których jeszcze nie wysłano</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="bulk_platform_migration_mode" id="bulk_platform_migration_mode_resend" value="resend_all">
                        <label class="form-check-label" for="bulk_platform_migration_mode_resend">Wyślij ponownie do wszystkich z ważnym dostępem</label>
                    </div>
                </div>
                <div id="bulkPlatformMigrationEmailPreviewLoading" class="text-muted small py-2">
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Ładowanie podglądu wiadomości…
                </div>
                <div id="bulkPlatformMigrationEmailPreviewError" class="alert alert-danger d-none" role="alert"></div>
                <div id="bulkPlatformMigrationEmailPreviewContent" class="d-none">
                    <p class="small mb-2" id="bulkPlatformMigrationEmailPreviewNotice"></p>
                    <p class="small text-muted mb-2" id="bulkPlatformMigrationEmailPreviewVariant"></p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1" for="bulkPlatformMigrationEmailPreviewTo">Przykładowy odbiorca</label>
                        <input type="text" class="form-control form-control-sm" id="bulkPlatformMigrationEmailPreviewTo" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1" for="bulkPlatformMigrationEmailPreviewSubject">Temat</label>
                        <input type="text" class="form-control form-control-sm" id="bulkPlatformMigrationEmailPreviewSubject" readonly>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold mb-1" for="bulkPlatformMigrationEmailPreviewFrame">Treść (podgląd HTML)</label>
                        <iframe id="bulkPlatformMigrationEmailPreviewFrame"
                                title="Podgląd zbiorczej wiadomości e-mail"
                                class="w-100 border rounded bg-white"
                                style="height: 22rem;"></iframe>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Anuluj
                </button>
                <form action="{{ route('online-courses.enrollments.send-platform-migration-bulk', $online_course) }}" method="POST" class="d-inline" data-loading-submit>
                    @csrf
                    <input type="hidden" name="mode" id="bulk_platform_migration_mode_input" value="unsent">
                    <button type="submit" class="btn btn-primary" id="bulkPlatformMigrationEmailSendBtn" disabled data-loading-text="Zlecanie…">
                        <i class="bi bi-send me-1"></i> Zatwierdź i wyślij
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
            var modal = document.getElementById('bulkPlatformMigrationEmailModal');
            if (!modal) {
                return;
            }
            var previewBaseUrl = modal.getAttribute('data-preview-url') || '';
            var loadingEl = document.getElementById('bulkPlatformMigrationEmailPreviewLoading');
            var errorEl = document.getElementById('bulkPlatformMigrationEmailPreviewError');
            var contentEl = document.getElementById('bulkPlatformMigrationEmailPreviewContent');
            var noticeEl = document.getElementById('bulkPlatformMigrationEmailPreviewNotice');
            var variantEl = document.getElementById('bulkPlatformMigrationEmailPreviewVariant');
            var toEl = document.getElementById('bulkPlatformMigrationEmailPreviewTo');
            var subjectEl = document.getElementById('bulkPlatformMigrationEmailPreviewSubject');
            var frameEl = document.getElementById('bulkPlatformMigrationEmailPreviewFrame');
            var hiddenMode = document.getElementById('bulk_platform_migration_mode_input');
            var sendBtn = document.getElementById('bulkPlatformMigrationEmailSendBtn');
            var previewAbort = null;

            function currentMode() {
                var checked = modal.querySelector('input[name="bulk_platform_migration_mode"]:checked');
                return (checked && checked.value) ? checked.value : 'unsent';
            }

            function loadPreview() {
                var mode = currentMode();
                if (hiddenMode) {
                    hiddenMode.value = mode;
                }
                if (previewAbort) {
                    previewAbort.abort();
                }
                loadingEl.classList.remove('d-none');
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
                contentEl.classList.add('d-none');
                sendBtn.disabled = true;
                if (window.PneSetEmailPreviewFrame) {
                    window.PneSetEmailPreviewFrame(frameEl, '');
                }

                previewAbort = new AbortController();
                var timeoutId = setTimeout(function () { previewAbort.abort(); }, 15000);
                fetch(previewBaseUrl + (previewBaseUrl.indexOf('?') === -1 ? '?' : '&') + 'mode=' + encodeURIComponent(mode), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: previewAbort.signal,
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        return { res: res, data: data };
                    });
                }).then(function (payload) {
                    clearTimeout(timeoutId);
                    loadingEl.classList.add('d-none');
                    var data = payload.data || {};
                    if (!payload.res.ok || !data.success) {
                        errorEl.textContent = data.error || 'Nie udało się pobrać podglądu.';
                        errorEl.classList.remove('d-none');
                        return;
                    }
                    var count = data.recipient_count != null ? data.recipient_count : 0;
                    noticeEl.textContent = (data.sample_notice || '') + (count ? ' Liczba odbiorców: ' + count + '.' : '');
                    variantEl.textContent = data.variant_label || '';
                    toEl.value = data.to || '';
                    subjectEl.value = data.subject || '';
                    if (window.PneSetEmailPreviewFrame) {
                        window.PneSetEmailPreviewFrame(frameEl, data.body_html || '');
                    }
                    contentEl.classList.remove('d-none');
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="bi bi-send me-1"></i> Zatwierdź i wyślij (' + count + ')';
                }).catch(function (e) {
                    clearTimeout(timeoutId);
                    loadingEl.classList.add('d-none');
                    errorEl.textContent = (e && e.name === 'AbortError')
                        ? 'Podgląd nie odpowiedział w 15 s. Zmień tryb albo otwórz okno ponownie.'
                        : 'Nie udało się pobrać podglądu wiadomości.';
                    errorEl.classList.remove('d-none');
                });
            }

            modal.addEventListener('show.bs.modal', loadPreview);
            modal.addEventListener('change', function (event) {
                var target = event.target;
                if (target && target.name === 'bulk_platform_migration_mode') {
                    loadPreview();
                }
            });
        })();
    </script>
    @endpush
@endonce
