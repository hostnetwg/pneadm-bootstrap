<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Informacje o przeniesieniu kursów z nowoczesna-edukacja.pl</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if(session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
            @endif
            <div id="migrationNoticeLiveAlert" class="d-none" role="status"></div>

            @include('participants.partials.mail-system-config-alert')

            <p class="mb-3">
                <a href="{{ route('online-courses.index') }}" class="btn btn-outline-secondary btn-sm">Wróć do kursów online</a>
            </p>

            <div class="alert alert-light border">
                Jeden list na osobę, z listą jej kursów przeniesionych ze starej platformy. List <strong>nie zakłada konta</strong>.
                Osoba bez konta dostaje link do rejestracji na ten sam adres. Osoba z kontem dostaje link do logowania.
                W tabeli są tylko ważne dostępy ze źródła przeniesienia. Zakupy złożone już na pnedu.pl, zapisy bezpłatne i wpisy ręczne są pominięte.
            </div>

            <div id="migrationNoticeBatchAlert" class="alert alert-info {{ ($batch['active'] ?? false) ? '' : 'd-none' }} mb-3 sticky-top shadow-sm" style="z-index: 1020;" role="alert"
                 data-status-url="{{ route('online-courses.migration-notice.batch-status') }}"
                 data-cancel-url="{{ route('online-courses.migration-notice.batch-cancel') }}">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="spinner-border spinner-border-sm sending-emails-spinner {{ ($batch['active'] ?? false) ? '' : 'd-none' }}" aria-hidden="true"></span>
                    <span class="sending-emails-text">
                        @if($batch['active'] ?? false)
                            <strong>Trwa wysyłanie e-maili (informacja o przeniesieniu).</strong>
                            Wykonano <strong>{{ (int) $batch['processed'] }} z {{ (int) $batch['total'] }}</strong>,
                            błędów: <strong>{{ (int) $batch['failed'] }}</strong>.
                        @else
                            <strong>Trwa wysyłanie e-maili.</strong> Na koniec zobaczysz komunikat z potwierdzeniem.
                        @endif
                    </span>
                    <span class="sending-emails-cancel-wrap {{ ($batch['active'] ?? false) ? '' : 'd-none' }} ms-auto">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="cancelMigrationNoticeBatchBtn">Przerwij wysyłkę</button>
                    </span>
                </div>
                <div class="progress" style="height: 10px;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ ($batch['active'] ?? false) && (int) $batch['total'] > 0 ? (int) round(100 * (int) $batch['processed'] / (int) $batch['total']) : 0 }}" aria-label="Postęp wysyłki informacji o przeniesieniu">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="migrationNoticeProgressBar" style="width: {{ ($batch['active'] ?? false) && (int) $batch['total'] > 0 ? (int) round(100 * (int) $batch['processed'] / (int) $batch['total']) : 0 }}%"></div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">Wariant: nie ma konta na pnedu.pl</div>
                        <div class="card-body">
                            <p class="small text-muted mb-2">Temat: {{ $sampleWithoutAccount['subject'] }}</p>
                            <iframe title="Podgląd listu bez konta" class="w-100 border rounded bg-white" style="height: 28rem;"
                                    srcdoc="{{ $sampleWithoutAccount['body_html'] }}"></iframe>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">Wariant: konto na pnedu.pl już jest</div>
                        <div class="card-body">
                            <p class="small text-muted mb-2">Temat: {{ $sampleWithAccount['subject'] }}</p>
                            <iframe title="Podgląd listu z kontem" class="w-100 border rounded bg-white" style="height: 28rem;"
                                    srcdoc="{{ $sampleWithAccount['body_html'] }}"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#migrationNoticeBulkModal"
                        data-count-url="{{ route('online-courses.migration-notice.unsent-count', $filters) }}"
                        @disabled($batch['active'] ?? false)>
                    <i class="bi bi-send me-1"></i> Wyślij e-mail z informacją o przeniesieniu
                </button>
                <span class="text-muted small">Do wysłania przy obecnych filtrach: <strong id="migrationNoticeUnsentCount">{{ $unsentCount }}</strong> osób, które jeszcze nie dostały listu.</span>
            </div>

            <form method="get" action="{{ route('online-courses.migration-notice.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label for="migration_notice_q" class="form-label">Szukaj</label>
                    <input id="migration_notice_q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Imię, nazwisko lub e-mail">
                </div>
                <div class="col-md-4">
                    <label for="migration_notice_course" class="form-label">Kurs online nagrany</label>
                    <select id="migration_notice_course" name="course_id" class="form-select">
                        <option value="">Wszystkie kursy</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}" @selected($filters['course_id'] === $course->id)>{{ $course->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="migration_notice_account" class="form-label">Konto pnedu.pl</label>
                    <select id="migration_notice_account" name="account" class="form-select">
                        <option value="all" @selected($filters['account'] === 'all')>Wszyscy</option>
                        <option value="yes" @selected($filters['account'] === 'yes')>Ma konto</option>
                        <option value="no" @selected($filters['account'] === 'no')>Brak konta</option>
                    </select>
                </div>
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">Filtruj</button>
                    <a href="{{ route('online-courses.migration-notice.index') }}" class="btn btn-outline-secondary">Wyczyść</a>
                </div>
            </form>

            @php
                $nextDirection = $filters['direction'] === 'desc' ? 'asc' : 'desc';
                $sortUrl = route('online-courses.migration-notice.index', array_merge($filters, ['direction' => $nextDirection]));
            @endphp

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Imię</th>
                            <th>Nazwisko</th>
                            <th>E-mail</th>
                            <th>
                                <a href="{{ $sortUrl }}">Liczba kursów
                                    @if($filters['direction'] === 'asc')
                                        <i class="bi bi-sort-numeric-up"></i>
                                    @else
                                        <i class="bi bi-sort-numeric-down"></i>
                                    @endif
                                </a>
                            </th>
                            <th>Konto pnedu.pl</th>
                            <th>Wysłany e-mail</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($people as $person)
                            @php
                                $email = (string) $person->email;
                                $stats = $sendStats[$email] ?? null;
                                $hasAccount = is_array($accounts) ? isset($accounts[$email]) : null;
                                $sentCount = (int) ($stats['sent_count'] ?? 0);
                                $lastSent = $stats['last_sent_at'] ?? null;
                            @endphp
                            <tr data-migration-email="{{ $email }}">
                                <td>{{ $person->first_name ?: '—' }}</td>
                                <td>{{ $person->last_name ?: '—' }}</td>
                                <td>{{ $email }}</td>
                                <td>{{ (int) $person->course_count }}</td>
                                <td>
                                    @if($hasAccount === null)
                                        <span class="text-muted">nie sprawdzono</span>
                                    @elseif($hasAccount)
                                        <span class="badge bg-success">tak</span>
                                    @else
                                        <span class="badge bg-secondary">nie</span>
                                    @endif
                                </td>
                                <td class="migration-notice-sent">
                                    @if($sentCount > 0 && $lastSent)
                                        {{ $lastSent->timezone('Europe/Warsaw')->format('d.m.Y H:i') }} ({{ $sentCount }})
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#migrationNoticeOneModal"
                                            data-preview-url="{{ route('online-courses.migration-notice.preview', ['email' => $email]) }}"
                                            data-send-email="{{ $email }}">
                                        Wyślij e-mail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted">Brak osób dla wybranych filtrów.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $people->links() }}
        </div>
    </div>

    <div class="modal fade" id="migrationNoticeBulkModal" tabindex="-1" aria-labelledby="migrationNoticeBulkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="migrationNoticeBulkModalLabel">Wyślij e-mail z informacją o przeniesieniu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p id="migrationNoticeBulkCount" class="mb-2">Sprawdzanie liczby odbiorców…</p>
                    <p class="mb-0">
                        Każda osoba dostaje jeden list. Wariant zależy od tego, czy ma już konto na pnedu.pl. Oba wzory są na stronie powyżej.
                        Lista kursów w liście obejmuje wszystkie ważne kursy tej osoby ze starej platformy, także gdy filtr pokazuje jeden kurs.
                        Wysyłka obejmuje tylko osoby, które tego listu jeszcze nie dostały. Konta nie są zakładane.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <form method="post" action="{{ route('online-courses.migration-notice.send-bulk') }}" data-loading-submit>
                        @csrf
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                        <input type="hidden" name="course_id" value="{{ $filters['course_id'] }}">
                        <input type="hidden" name="account" value="{{ $filters['account'] }}">
                        <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                        <button type="submit" class="btn btn-primary" id="migrationNoticeBulkSendBtn" disabled data-loading-text="Zlecanie…">
                            Zatwierdź i wyślij
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="migrationNoticeOneModal" tabindex="-1" aria-labelledby="migrationNoticeOneModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="migrationNoticeOneModalLabel">Podgląd e-maila</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <div id="migrationNoticeOneLoading" class="text-muted small py-3 text-center">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Ładowanie podglądu…
                    </div>
                    <div id="migrationNoticeOneError" class="alert alert-danger d-none" role="alert"></div>
                    <div id="migrationNoticeOneContent" class="d-none">
                        <p class="small text-muted mb-2" id="migrationNoticeOneVariant"></p>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1" for="migrationNoticeOneTo">Do</label>
                            <input type="text" class="form-control form-control-sm" id="migrationNoticeOneTo" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1" for="migrationNoticeOneSubject">Temat</label>
                            <input type="text" class="form-control form-control-sm" id="migrationNoticeOneSubject" readonly>
                        </div>
                        <iframe id="migrationNoticeOneFrame" title="Podgląd wiadomości do jednej osoby" class="w-100 border rounded bg-white" style="height: 24rem;"></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <form id="migrationNoticeOneForm" method="post" action="{{ route('online-courses.migration-notice.send') }}">
                        @csrf
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                        <input type="hidden" name="course_id" value="{{ $filters['course_id'] }}">
                        <input type="hidden" name="account" value="{{ $filters['account'] }}">
                        <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                        <input type="hidden" name="email" id="migrationNoticeOneEmail" value="">
                        <button type="submit" class="btn btn-primary" id="migrationNoticeOneSendBtn" disabled data-loading-text="Wysyłanie…">
                            Wyślij e-mail
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('online-courses.enrollments.partials.email-preview-frame-helper')

    @push('scripts')
    <script>
        (function () {
            var bulkModal = document.getElementById('migrationNoticeBulkModal');
            var countEl = document.getElementById('migrationNoticeBulkCount');
            var sendBtn = document.getElementById('migrationNoticeBulkSendBtn');
            if (bulkModal) {
                bulkModal.addEventListener('show.bs.modal', function (event) {
                    var trigger = event.relatedTarget;
                    var url = trigger && trigger.getAttribute('data-count-url');
                    if (!url || !countEl || !sendBtn) {
                        return;
                    }
                    countEl.textContent = 'Sprawdzanie liczby odbiorców…';
                    sendBtn.disabled = true;
                    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            var count = Number(data.unsent_count || 0);
                            countEl.innerHTML = 'Zostanie wysłany jeden list do <strong>' + count + '</strong> osób, które jeszcze go nie dostały.';
                            sendBtn.disabled = count < 1;
                        })
                        .catch(function () {
                            countEl.textContent = 'Nie udało się policzyć odbiorców.';
                        });
                });
            }

            var oneModal = document.getElementById('migrationNoticeOneModal');
            if (oneModal) {
                var loadingEl = document.getElementById('migrationNoticeOneLoading');
                var errorEl = document.getElementById('migrationNoticeOneError');
                var contentEl = document.getElementById('migrationNoticeOneContent');
                var variantEl = document.getElementById('migrationNoticeOneVariant');
                var toEl = document.getElementById('migrationNoticeOneTo');
                var subjectEl = document.getElementById('migrationNoticeOneSubject');
                var frameEl = document.getElementById('migrationNoticeOneFrame');
                var emailEl = document.getElementById('migrationNoticeOneEmail');
                var oneSend = document.getElementById('migrationNoticeOneSendBtn');

                oneModal.addEventListener('show.bs.modal', function (event) {
                    var trigger = event.relatedTarget;
                    var url = trigger && trigger.getAttribute('data-preview-url');
                    var email = trigger && trigger.getAttribute('data-send-email');
                    loadingEl.classList.remove('d-none');
                    errorEl.classList.add('d-none');
                    contentEl.classList.add('d-none');
                    oneSend.disabled = true;
                    emailEl.value = email || '';
                    if (window.PneSetEmailPreviewFrame) {
                        window.PneSetEmailPreviewFrame(frameEl, '');
                    }
                    if (!url) {
                        return;
                    }
                    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                        .then(function (result) {
                            loadingEl.classList.add('d-none');
                            if (!result.ok || !result.data.success) {
                                errorEl.textContent = (result.data && result.data.error) || 'Nie udało się przygotować podglądu.';
                                errorEl.classList.remove('d-none');
                                return;
                            }
                            var sent = Number(result.data.sent_count || 0);
                            variantEl.textContent = result.data.has_pnedu_account
                                ? 'Wariant: osoba ma konto na pnedu.pl.'
                                : 'Wariant: brak konta na pnedu.pl. List nie zakłada konta.';
                            if (sent > 0) {
                                variantEl.textContent += ' Ten adres dostał już ten list (' + sent + '). To będzie kolejna wysyłka.';
                            }
                            toEl.value = result.data.to || '';
                            subjectEl.value = result.data.subject || '';
                            if (window.PneSetEmailPreviewFrame) {
                                window.PneSetEmailPreviewFrame(frameEl, result.data.body_html || '');
                            }
                            contentEl.classList.remove('d-none');
                            oneSend.disabled = false;
                        })
                        .catch(function () {
                            loadingEl.classList.add('d-none');
                            errorEl.textContent = 'Nie udało się przygotować podglądu.';
                            errorEl.classList.remove('d-none');
                        });
                });

                var oneForm = document.getElementById('migrationNoticeOneForm');
                if (oneForm) {
                    oneForm.addEventListener('submit', function (event) {
                        event.preventDefault();
                        if (oneForm.dataset.busy === '1' || !oneSend || oneSend.disabled) {
                            return;
                        }
                        oneForm.dataset.busy = '1';
                        var originalHtml = oneSend.innerHTML;
                        oneSend.disabled = true;
                        oneSend.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Wysyłanie…';
                        var body = new URLSearchParams(new FormData(oneForm));
                        var headers = {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        };
                        if (body.get('_token')) {
                            headers['X-CSRF-TOKEN'] = body.get('_token');
                        }
                        fetch(oneForm.action, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: headers,
                            body: body
                        }).then(function (r) {
                            return r.json().then(function (data) { return { ok: r.ok, data: data }; });
                        }).then(function (result) {
                            oneForm.dataset.busy = '0';
                            oneSend.disabled = false;
                            oneSend.innerHTML = originalHtml;
                            if (!result.ok || !result.data.success) {
                                errorEl.textContent = (result.data && result.data.error) || 'Nie udało się wysłać e-maila.';
                                errorEl.classList.remove('d-none');
                                return;
                            }
                            var email = result.data.email || '';
                            var row = email && window.CSS && CSS.escape
                                ? document.querySelector('tr[data-migration-email="' + CSS.escape(email) + '"]')
                                : null;
                            var sentCell = row && row.querySelector('.migration-notice-sent');
                            if (sentCell) {
                                sentCell.textContent = result.data.sent_label || '—';
                            }
                            var unsentEl = document.getElementById('migrationNoticeUnsentCount');
                            if (unsentEl && result.data.unsent_count !== undefined && result.data.unsent_count !== null) {
                                unsentEl.textContent = String(result.data.unsent_count);
                            }
                            var live = document.getElementById('migrationNoticeLiveAlert');
                            if (live) {
                                live.className = 'alert alert-success';
                                live.textContent = 'E-mail o przeniesieniu kursów został wysłany na adres ' + email + '.';
                            }
                            var modal = window.bootstrap && window.bootstrap.Modal.getInstance(oneModal);
                            if (modal) {
                                modal.hide();
                            }
                        }).catch(function () {
                            oneForm.dataset.busy = '0';
                            oneSend.disabled = false;
                            oneSend.innerHTML = originalHtml;
                            errorEl.textContent = 'Nie udało się wysłać e-maila.';
                            errorEl.classList.remove('d-none');
                        });
                    });
                }
            }

            var alertEl = document.getElementById('migrationNoticeBatchAlert');
            var statusUrl = alertEl && alertEl.getAttribute('data-status-url');
            var cancelUrl = alertEl && alertEl.getAttribute('data-cancel-url');
            if (!alertEl || !statusUrl) {
                return;
            }
            var pollId = null;
            var seenActive = !alertEl.classList.contains('d-none');

            function csrfHeaders() {
                var token = (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content'))
                    || (document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value);
                var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' };
                if (token) {
                    headers['X-CSRF-TOKEN'] = token;
                }
                return { headers: headers, body: new URLSearchParams({ _token: token || '' }) };
            }

            function setProgressBar(processed, total, extraClass) {
                var bar = document.getElementById('migrationNoticeProgressBar');
                var wrap = bar && bar.parentElement;
                var pct = total > 0 ? Math.min(100, Math.round(100 * processed / total)) : 0;
                if (!bar) {
                    return;
                }
                bar.style.width = pct + '%';
                bar.className = 'progress-bar' + (extraClass ? ' ' + extraClass : ' progress-bar-striped progress-bar-animated');
                if (wrap) {
                    wrap.setAttribute('aria-valuenow', String(pct));
                }
            }

            function setSpinnerVisible(visible) {
                var spinner = alertEl.querySelector('.sending-emails-spinner');
                if (!spinner) {
                    return;
                }
                spinner.classList.toggle('d-none', !visible);
            }

            function stopPolling() {
                if (pollId) {
                    clearInterval(pollId);
                    pollId = null;
                }
            }

            function showBanner(kind) {
                alertEl.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-warning', 'alert-danger');
                alertEl.classList.add(kind || 'alert-info');
                alertEl.scrollIntoView({ block: 'nearest' });
            }

            function wireCancel() {
                var cancelWrap = alertEl.querySelector('.sending-emails-cancel-wrap');
                var textEl = alertEl.querySelector('.sending-emails-text');
                if (!cancelWrap || !cancelUrl) {
                    return;
                }
                cancelWrap.classList.remove('d-none');
                var btn = cancelWrap.querySelector('#cancelMigrationNoticeBatchBtn');
                if (!btn || btn.dataset.wired) {
                    return;
                }
                btn.dataset.wired = '1';
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    var csrf = csrfHeaders();
                    fetch(cancelUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: csrf.headers,
                        body: csrf.body
                    }).then(function (r) { return r.json(); }).then(function () {
                        stopPolling();
                        cancelWrap.classList.add('d-none');
                        setSpinnerVisible(false);
                        showBanner('alert-warning');
                        if (textEl) {
                            textEl.innerHTML = '<strong>Wysyłka przerwana.</strong> Kończę odczyt kolejki…';
                        }
                        update();
                    }).catch(function () {
                        btn.disabled = false;
                    });
                });
            }

            function update() {
                var textEl = alertEl.querySelector('.sending-emails-text');
                var cancelWrap = alertEl.querySelector('.sending-emails-cancel-wrap');
                fetch(statusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.active) {
                            stopPolling();
                            if (cancelWrap) {
                                cancelWrap.classList.add('d-none');
                            }
                            setSpinnerVisible(false);
                            if (!seenActive) {
                                alertEl.classList.add('d-none');
                                return;
                            }
                            if (data.state === 'finished') {
                                showBanner('alert-success');
                                setProgressBar(data.processed, data.total, 'bg-success');
                                if (textEl) {
                                    textEl.innerHTML =
                                        '<strong>Wysyłka zakończona.</strong> ' +
                                        'Wysłano/obsłużono <strong>' + data.processed + ' z ' + data.total + '</strong>, ' +
                                        'błędów: <strong>' + data.failed + '</strong>. Odświeżanie listy…';
                                }
                                setTimeout(function () { window.location.reload(); }, 2500);
                            } else if (data.state === 'cancelled') {
                                showBanner('alert-warning');
                                setProgressBar(data.processed, data.total, 'bg-warning');
                                if (textEl) {
                                    textEl.innerHTML =
                                        '<strong>Wysyłka przerwana.</strong> ' +
                                        'Wykonano <strong>' + data.processed + ' z ' + data.total + '</strong>, ' +
                                        'błędów: <strong>' + data.failed + '</strong>.';
                                }
                            } else {
                                alertEl.classList.add('d-none');
                            }
                            return;
                        }
                        seenActive = true;
                        showBanner('alert-info');
                        setSpinnerVisible(true);
                        setProgressBar(data.processed, data.total);
                        if (textEl) {
                            textEl.innerHTML =
                                '<strong>Trwa wysyłanie e-maili (informacja o przeniesieniu).</strong> ' +
                                'Wykonano <strong>' + data.processed + ' z ' + data.total + '</strong>, ' +
                                'błędów: <strong>' + data.failed + '</strong>.';
                        }
                        wireCancel();
                        if (!pollId) {
                            pollId = setInterval(update, 2000);
                        }
                    })
                    .catch(function () {});
            }

            var bulkForm = bulkModal && bulkModal.querySelector('form');
            if (bulkForm) {
                bulkForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (bulkForm.dataset.busy === '1') {
                        return;
                    }
                    bulkForm.dataset.busy = '1';
                    if (sendBtn) {
                        sendBtn.disabled = true;
                    }
                    var modal = window.bootstrap && window.bootstrap.Modal.getInstance(bulkModal);
                    if (modal) {
                        modal.hide();
                    }
                    seenActive = true;
                    showBanner('alert-info');
                    setSpinnerVisible(true);
                    setProgressBar(0, 0);
                    var textEl = alertEl.querySelector('.sending-emails-text');
                    if (textEl) {
                        textEl.innerHTML = '<strong>Przygotowuję wysyłkę w kolejce.</strong> Za chwilę pojawi się postęp i przycisk przerwania.';
                    }
                    var body = new URLSearchParams(new FormData(bulkForm));
                    var headers = csrfHeaders().headers;
                    fetch(bulkForm.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: headers,
                        body: body
                    }).then(function (r) {
                        return r.json().then(function (data) { return { ok: r.ok, data: data }; });
                    }).then(function (result) {
                        bulkForm.dataset.busy = '0';
                        if (!result.ok || !result.data.success) {
                            setSpinnerVisible(false);
                            showBanner('alert-danger');
                            if (textEl) {
                                textEl.textContent = (result.data && result.data.error) || 'Nie udało się zlecić wysyłki.';
                            }
                            if (sendBtn) {
                                sendBtn.disabled = false;
                            }
                            return;
                        }
                        update();
                    }).catch(function () {
                        bulkForm.dataset.busy = '0';
                        setSpinnerVisible(false);
                        showBanner('alert-danger');
                        if (textEl) {
                            textEl.textContent = 'Nie udało się zlecić wysyłki.';
                        }
                        if (sendBtn) {
                            sendBtn.disabled = false;
                        }
                    });
                });
            }

            if (seenActive) {
                wireCancel();
            }
            update();
        })();
    </script>
    @endpush
</x-app-layout>
