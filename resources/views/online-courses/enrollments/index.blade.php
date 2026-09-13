<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Dostępy: {{ $online_course->title }}</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @include('online-courses.partials.navigation-tabs', [
                'onlineCourse' => $online_course,
                'activeTab' => 'enrollments',
            ])

            @include('participants.partials.mail-system-config-alert')

            <div id="sendingMigrationEmailsAlert" class="alert alert-info d-none mb-3" role="alert"
                 data-status-url="{{ route('online-courses.enrollments.platform-migration-email-status', $online_course) }}"
                 data-cancel-url="{{ route('online-courses.enrollments.platform-migration-email-cancel', $online_course) }}">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="spinner-border spinner-border-sm sending-emails-spinner" aria-hidden="true"></span>
                    <span class="sending-emails-text"><strong>Trwa wysyłanie e-maili.</strong> Na koniec zobaczysz komunikat z potwierdzeniem.</span>
                    <span class="sending-emails-cancel-wrap d-none ms-auto">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="cancelMigrationEmailBatchBtn">Przerwij wysyłkę</button>
                    </span>
                </div>
                <div class="progress" style="height: 10px;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Postęp wysyłki e-maili o przeniesieniu kursu">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="sendingMigrationEmailsProgressBar" style="width: 0%"></div>
                </div>
            </div>

            <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('online-courses.enrollments.create', $online_course) }}" class="btn btn-primary">Dodaj dostęp</a>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importPubligoCsvModal">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Import CSV (Publigo)
                </button>
                <a href="{{ route('online-courses.edit', $online_course) }}" class="btn btn-outline-secondary">Treść kursu</a>
                @if(!$online_course->certificate_template_id)
                    <span class="text-muted small ms-2">
                        <i class="bi bi-info-circle"></i> Aby wydawać zaświadczenia, przypisz szablon w edycji kursu online.
                    </span>
                @endif
            </div>

            @php
                $mailStats = $migrationMailStats ?? ['sent' => 0, 'queued' => 0, 'failed_without_sent' => 0, 'eligible' => 0];
                $mailSent = (int) ($mailStats['sent'] ?? 0);
                $mailEligible = (int) ($mailStats['eligible'] ?? 0);
                $mailPct = $mailEligible > 0 ? min(100, (int) round($mailSent / $mailEligible * 100)) : 0;
            @endphp
            <div class="card shadow-sm mb-3">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
                        <div>
                            <i class="bi bi-envelope-paper me-1 text-primary"></i>
                            <strong>E-mail: kurs na pnedu.pl</strong>
                            <div class="small text-muted mt-1">
                                Informacja, że dostęp został przeniesiony na nową platformę. Zbiorczo tylko osoby z <strong>ważnym dostępem</strong>.
                            </div>
                        </div>
                        <span class="badge {{ $mailSent > 0 ? 'bg-success' : 'bg-secondary' }} fs-6">
                            {{ $mailSent }}/{{ $mailEligible }}
                        </span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkPlatformMigrationEmailModal">
                            <i class="bi bi-send me-1"></i> Wyślij e-maile zbiorczo
                        </button>
                    </div>
                    <p class="mb-2 mb-md-1">
                        Wysłano do <strong>{{ $mailSent }}</strong> z <strong>{{ $mailEligible }}</strong> osób z ważnym dostępem.
                    </p>
                    <div class="progress mb-2" style="height: 6px;" role="progressbar" aria-valuenow="{{ $mailPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Postęp wysyłki e-maila o przeniesieniu kursu">
                        <div class="progress-bar {{ $mailSent > 0 ? 'bg-success' : 'bg-secondary' }}" style="width: {{ $mailPct }}%"></div>
                    </div>
                    @if(($mailStats['queued'] ?? 0) > 0 || ($mailStats['failed_without_sent'] ?? 0) > 0)
                        <p class="small text-muted mb-0">
                            @if(($mailStats['queued'] ?? 0) > 0)
                                <i class="bi bi-clock text-warning me-1"></i>{{ $mailStats['queued'] }} w kolejce
                            @endif
                            @if(($mailStats['failed_without_sent'] ?? 0) > 0)
                                @if(($mailStats['queued'] ?? 0) > 0)<span class="mx-1">·</span>@endif
                                <i class="bi bi-exclamation-circle text-danger me-1"></i>{{ $mailStats['failed_without_sent'] }} bez udanej wysyłki
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body py-3">
                    <form method="GET" action="{{ route('online-courses.enrollments.index', $online_course) }}" class="row g-2 align-items-end">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
                        <div class="col-md-3">
                            <label for="filter-q" class="form-label small text-muted mb-1">Szukaj</label>
                            <input type="search" name="q" id="filter-q" class="form-control form-control-sm"
                                   value="{{ $filters['q'] }}" placeholder="E-mail, imię, nazwisko, telefon, ID Publigo…"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-2">
                            <label for="filter-access" class="form-label small text-muted mb-1">Dostęp</label>
                            <select name="access" id="filter-access" class="form-select form-select-sm">
                                <option value="all" @selected($filters['access'] === 'all')>Wszystkie</option>
                                <option value="active" @selected($filters['access'] === 'active')>Ważny</option>
                                <option value="expired" @selected($filters['access'] === 'expired')>Wygasły</option>
                                <option value="unlimited" @selected($filters['access'] === 'unlimited')>Bezterminowy</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter-pnedu" class="form-label small text-muted mb-1">Konto pnedu.pl</label>
                            <select name="pnedu" id="filter-pnedu" class="form-select form-select-sm">
                                <option value="all" @selected($filters['pnedu'] === 'all')>Wszystkie</option>
                                <option value="yes" @selected($filters['pnedu'] === 'yes')>Tak</option>
                                <option value="no" @selected($filters['pnedu'] === 'no')>Nie</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter-certificate" class="form-label small text-muted mb-1">Zaświadczenie</label>
                            <select name="certificate" id="filter-certificate" class="form-select form-select-sm">
                                <option value="all" @selected($filters['certificate'] === 'all')>Wszystkie</option>
                                <option value="yes" @selected($filters['certificate'] === 'yes')>Jest numer</option>
                                <option value="no" @selected($filters['certificate'] === 'no')>Brak numeru</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter-mail" class="form-label small text-muted mb-1">E-mail pnedu.pl</label>
                            <select name="mail" id="filter-mail" class="form-select form-select-sm">
                                <option value="all" @selected($filters['mail'] === 'all')>Wszystkie</option>
                                <option value="unsent" @selected($filters['mail'] === 'unsent')>Nie wysłano</option>
                                <option value="sent" @selected($filters['mail'] === 'sent')>Wysłano</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter-source" class="form-label small text-muted mb-1">Źródło</label>
                            <select name="source" id="filter-source" class="form-select form-select-sm">
                                <option value="all" @selected($filters['source'] === 'all')>Wszystkie</option>
                                @foreach($accessSources as $source)
                                    <option value="{{ $source }}" @selected($filters['source'] === $source)>{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search me-1"></i>Filtruj
                            </button>
                            @if($filtersActive)
                                <a href="{{ route('online-courses.enrollments.index', $online_course) }}" class="btn btn-outline-secondary btn-sm">
                                    Wyczyść
                                </a>
                            @endif
                            <span class="small text-muted align-self-center">
                                {{ $filtersActive ? 'Wyników' : 'Razem' }}: {{ $enrollments->total() }}
                            </span>
                        </div>
                    </form>
                </div>
            </div>

            @php
                $sort = $filters['sort'];
                $dir = $filters['dir'];
                $sortUrl = function (string $column) use ($online_course, $sort, $dir) {
                    $newDir = 'asc';
                    if ($sort === $column) {
                        $newDir = $dir === 'asc' ? 'desc' : 'asc';
                    } elseif (in_array($column, ['expires', 'pnedu', 'certificate'], true)) {
                        $newDir = 'desc';
                    }

                    return route('online-courses.enrollments.index', [
                        'online_course' => $online_course,
                        ...request()->except(['page', 'sort', 'dir']),
                        'sort' => $column,
                        'dir' => $newDir,
                    ]);
                };
                $sortIcon = function (string $column) use ($sort, $dir) {
                    if ($sort !== $column) {
                        return 'bi-arrow-down-up text-muted';
                    }

                    return ($dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down').' text-primary';
                };
            @endphp

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>
                                <a href="{{ $sortUrl('email') }}" class="text-decoration-none text-dark">
                                    E-mail <i class="bi {{ $sortIcon('email') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('name') }}" class="text-decoration-none text-dark">
                                    Imię i nazwisko <i class="bi {{ $sortIcon('name') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('pnedu') }}" class="text-decoration-none text-dark">
                                    Konto pnedu.pl <i class="bi {{ $sortIcon('pnedu') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('phone') }}" class="text-decoration-none text-dark">
                                    Telefon <i class="bi {{ $sortIcon('phone') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('expires') }}" class="text-decoration-none text-dark">
                                    Wygasa <i class="bi {{ $sortIcon('expires') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('source') }}" class="text-decoration-none text-dark">
                                    Źródło <i class="bi {{ $sortIcon('source') }}"></i>
                                </a>
                            </th>
                            <th>E-mail pnedu.pl</th>
                            <th>
                                <a href="{{ $sortUrl('certificate') }}" class="text-decoration-none text-dark">
                                    Nr zaświadczenia <i class="bi {{ $sortIcon('certificate') }}"></i>
                                </a>
                            </th>
                            <th>Zaświadczenie</th>
                            <th class="text-end">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enrollments as $e)
                            <tr>
                                <td>{{ $e->email }}</td>
                                <td>{{ trim(($e->first_name ?? '').' '.($e->last_name ?? '')) ?: '—' }}</td>
                                <td class="text-nowrap">
                                    @php
                                        $pneduUser = $pneduUsersByEmail[strtolower(trim((string) $e->email))] ?? null;
                                    @endphp
                                    @if($pneduUser)
                                        <a href="{{ route('admin.pnedu-users.show', $pneduUser) }}" class="badge bg-success text-decoration-none" title="Otwórz konto w panelu użytkowników pnedu.pl">
                                            <i class="bi bi-check-circle me-1"></i>Tak
                                        </a>
                                    @else
                                        <span class="badge bg-secondary" title="Brak konta z tym e-mailem na pnedu.pl">
                                            <i class="bi bi-person-x me-1"></i>Nie
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $e->phone ?: '—' }}</td>
                                <td class="text-nowrap">
                                    @if($e->access_expires_at)
                                        @if($e->hasExpiredAccess())
                                            <span class="text-danger">{{ $e->access_expires_at->timezone('Europe/Warsaw')->format('Y-m-d H:i') }}</span>
                                            <span class="badge bg-danger">wygasł</span>
                                        @else
                                            {{ $e->access_expires_at->timezone('Europe/Warsaw')->format('Y-m-d H:i') }}
                                        @endif
                                    @else
                                        bezterminowo
                                    @endif
                                </td>
                                <td>{{ $e->access_source ?: '—' }}</td>
                                <td>
                                    @include('online-courses.enrollments.partials.platform-migration-email-status', [
                                        'mailStatus' => $migrationMailStatusByEnrollmentId[$e->id] ?? [],
                                    ])
                                </td>
                                <td>
                                    @if ($e->certificate)
                                        <a href="{{ route('online-courses.enrollments.certificate.generate', [$online_course, $e]) }}">
                                            {{ $e->certificate->certificate_number }}
                                        </a>
                                        @if(!empty($e->certificate->file_path))
                                            <a href="{{ route('certificates.download-pdf', $e->certificate) }}" class="text-success ms-1 text-decoration-none" title="Pobierz plik PDF z serwera (bez generowania)">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
                                        @endif
                                        @php
                                            $downloadCount = (int) ($e->certificate->download_count ?? 0);
                                            $lastDownloadedAt = $e->certificate->last_downloaded_at ?? null;
                                        @endphp
                                        <div class="mt-1">
                                            <span class="badge {{ $downloadCount > 0 ? 'bg-success' : 'bg-secondary' }}"
                                                  title="{{ $downloadCount > 0 && $lastDownloadedAt ? 'Ostatnie pobranie: ' . $lastDownloadedAt->format('d.m.Y H:i') : '' }}">
                                                Pobrane: {{ $downloadCount > 0 ? 'TAK' : 'NIE' }}@if($downloadCount > 0 && $lastDownloadedAt) ({{ $lastDownloadedAt->format('d.m.Y H:i') }})@endif
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @if ($e->certificate)
                                            <button type="button" class="btn btn-danger btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteCertificateModal{{ $e->certificate->id }}">
                                                <i class="bi bi-trash"></i> Usuń
                                            </button>
                                            @if(!empty($e->certificate->file_path))
                                                <form id="deleteCertificatePdfForm{{ $e->certificate->id }}" action="{{ route('certificates.delete-pdf', $e->certificate) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="button"
                                                            class="btn btn-outline-warning btn-sm"
                                                            title="Usuwa plik PDF, zachowuje zaświadczenie – potem wygeneruj ponownie"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#formConfirmModal"
                                                            data-confirm-title="Usuń plik PDF"
                                                            data-confirm-message="Usunąć tylko plik PDF tego zaświadczenia? Numer zaświadczenia zostanie zachowany – potem możesz wygenerować plik ponownie (np. po poprawce danych)."
                                                            data-confirm-form="#deleteCertificatePdfForm{{ $e->certificate->id }}"
                                                            data-confirm-btn-class="btn-warning"
                                                            data-confirm-btn-text="Usuń PDF"
                                                            data-confirm-header-class="bg-warning text-dark">
                                                        <i class="bi bi-file-earmark-pdf"></i> Usuń PDF
                                                    </button>
                                                </form>
                                            @endif
                                        @elseif($online_course->certificate_template_id)
                                            <a href="{{ route('online-courses.enrollments.certificate.store', [$online_course, $e]) }}" class="btn btn-primary btn-sm">Generuj</a>
                                        @else
                                            <span class="text-muted small">Brak szablonu</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-flex flex-column gap-1 align-items-end">
                                        <a href="{{ route('online-courses.enrollments.edit', [$online_course, $e]) }}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-success"
                                                data-bs-toggle="modal"
                                                data-bs-target="#platformMigrationEmailPreviewModal"
                                                data-preview-url="{{ route('online-courses.enrollments.preview-platform-migration', [$online_course, $e]) }}"
                                                data-send-url="{{ route('online-courses.enrollments.send-platform-migration', [$online_course, $e]) }}">
                                            <i class="bi bi-envelope me-1"></i>Wyślij e-mail
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteEnrollmentModal{{ $e->id }}">
                                            <i class="bi bi-trash"></i> Usuń
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-muted">{{ $filtersActive ? 'Brak wyników dla wybranych filtrów.' : 'Brak przypisań.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $enrollments->links() }}
        </div>
    </div>
    @push('scripts')
    <script>
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });

        (function () {
            var alertEl = document.getElementById('sendingMigrationEmailsAlert');
            var statusUrl = alertEl && alertEl.getAttribute('data-status-url');
            var cancelUrl = alertEl && alertEl.getAttribute('data-cancel-url');
            if (!alertEl || !statusUrl) {
                return;
            }

            var pollId = null;
            var seenActive = false;

            function csrfHeaders() {
                var token = (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content'))
                    || (document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value);
                var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
                if (token) {
                    headers['X-CSRF-TOKEN'] = token;
                }
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
                return { headers: headers, body: new URLSearchParams({ _token: token || '' }) };
            }

            function setProgressBar(processed, total, extraClass) {
                var bar = document.getElementById('sendingMigrationEmailsProgressBar');
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

            function wireCancel() {
                var cancelWrap = alertEl.querySelector('.sending-emails-cancel-wrap');
                var textEl = alertEl.querySelector('.sending-emails-text');
                if (!cancelWrap || !cancelUrl) {
                    return;
                }
                cancelWrap.classList.remove('d-none');
                var btn = cancelWrap.querySelector('#cancelMigrationEmailBatchBtn');
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
                        if (textEl) {
                            textEl.innerHTML = '<strong>Wysyłka przerwana.</strong>';
                        }
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
                                alertEl.classList.remove('d-none', 'alert-info', 'alert-warning', 'alert-danger');
                                alertEl.classList.add('alert-success');
                                setProgressBar(data.processed, data.total, 'bg-success');
                                if (textEl) {
                                    textEl.innerHTML =
                                        '<strong>Wysyłka zakończona.</strong> ' +
                                        'Wysłano/obsłużono <strong>' + data.processed + ' z ' + data.total + '</strong>, ' +
                                        'błędów: <strong>' + data.failed + '</strong>. Odświeżanie listy…';
                                }
                                setTimeout(function () { window.location.reload(); }, 2500);
                            } else if (data.state === 'cancelled') {
                                alertEl.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-danger');
                                alertEl.classList.add('alert-warning');
                                setProgressBar(data.processed, data.total, 'bg-warning');
                                if (textEl) {
                                    textEl.innerHTML =
                                        '<strong>Wysyłka przerwana.</strong> ' +
                                        'Wykonano <strong>' + data.processed + ' z ' + data.total + '</strong>, ' +
                                        'błędów: <strong>' + data.failed + '</strong>.';
                                }
                                setTimeout(function () { alertEl.classList.add('d-none'); }, 6000);
                            } else {
                                alertEl.classList.add('d-none');
                            }
                            return;
                        }

                        seenActive = true;
                        alertEl.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger');
                        alertEl.classList.add('alert-info');
                        setSpinnerVisible(true);
                        setProgressBar(data.processed, data.total);
                        if (textEl) {
                            textEl.innerHTML =
                                '<strong>Trwa wysyłanie e-maili o przeniesieniu kursu.</strong> ' +
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

            update();
        })();
    </script>
    @endpush

    @foreach ($enrollments as $e)
        <div class="modal fade" id="deleteEnrollmentModal{{ $e->id }}" tabindex="-1" aria-labelledby="deleteEnrollmentModalLabel{{ $e->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteEnrollmentModalLabel{{ $e->id }}">
                            <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia dostępu
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Czy na pewno chcesz usunąć dostęp dla <strong>{{ $e->email }}</strong>?</p>
                        <div class="bg-light p-3 rounded">
                            <ul class="mb-0">
                                <li><strong>Osoba:</strong> {{ trim(($e->first_name ?? '').' '.($e->last_name ?? '')) ?: '—' }}</li>
                                <li><strong>Kurs online:</strong> {{ $online_course->title }}</li>
                            </ul>
                        </div>
                        <p class="text-muted mt-3 mb-0">
                            <i class="bi bi-info-circle"></i>
                            Użytkownik straci dostęp do kursu. Zaświadczenie (jeśli istnieje) pozostaje w systemie.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <form action="{{ route('online-courses.enrollments.destroy', [$online_course, $e]) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Usuń dostęp
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if ($e->certificate)
            <div class="modal fade" id="deleteCertificateModal{{ $e->certificate->id }}" tabindex="-1" aria-labelledby="deleteCertificateModalLabel{{ $e->certificate->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteCertificateModalLabel{{ $e->certificate->id }}">
                                <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia zaświadczenia
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Czy na pewno chcesz usunąć zaświadczenie <strong>#{{ $e->certificate->id }}</strong>?</p>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">Szczegóły zaświadczenia:</h6>
                                <ul class="mb-0">
                                    <li><strong>Numer zaświadczenia:</strong> {{ $e->certificate->certificate_number ?? 'Brak numeru' }}</li>
                                    <li><strong>Osoba:</strong> {{ $e->first_name }} {{ $e->last_name }}</li>
                                    <li><strong>E-mail:</strong> {{ $e->email }}</li>
                                    <li><strong>Kurs online:</strong> {{ $online_course->title }}</li>
                                    <li><strong>Data wygenerowania:</strong> {{ $e->certificate->created_at ? $e->certificate->created_at->format('d.m.Y H:i') : 'Nieznana' }}</li>
                                </ul>
                            </div>
                            <p class="text-muted mt-3">
                                <i class="bi bi-info-circle"></i>
                                Zaświadczenie zostanie trwale usunięte z systemu. Ta operacja jest nieodwracalna!
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </button>
                            <form action="{{ route('certificates.destroy', $e->certificate->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Usuń zaświadczenie
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    @include('online-courses.enrollments.partials.import-modal')
    @include('online-courses.enrollments.partials.email-preview-frame-helper')
    @include('online-courses.enrollments.partials.platform-migration-email-preview-modal')
    @include('online-courses.enrollments.partials.platform-migration-email-bulk-modal')
    @include('participants.partials.form-confirm-modal')
</x-app-layout>
