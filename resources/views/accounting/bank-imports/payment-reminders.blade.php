<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Przypomnienia o zaległych płatnościach</h2>
            <a href="{{ route('accounting.bank-imports.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Import wyciągu
            </a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <a class="nav-link {{ $audience === 'standard' ? 'active' : '' }}"
                       href="{{ route('accounting.bank-imports.payment-reminders.index', ['audience' => 'standard']) }}">
                        Standardowe
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $audience === 'vip' ? 'active' : '' }}"
                       href="{{ route('accounting.bank-imports.payment-reminders.index', ['audience' => 'vip']) }}">
                        VIP
                    </a>
                </li>
            </ul>

            <form method="POST"
                  action="{{ route('accounting.bank-imports.payment-reminders.store') }}"
                  id="paymentReminderCampaignForm"
                  data-loading-submit
                  data-loading-text="Zlecam…">
                @csrf
                <input type="hidden" name="audience" value="{{ $audience }}">
                <div id="selectedIdsContainer"></div>

                <div class="card mb-3">
                    <div class="card-header fw-semibold">Opcje kampanii ({{ $audience === 'vip' ? 'VIP' : 'Standardowe' }})</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" value="1" id="send_email" name="send_email"
                                           @checked($options['send_email'])>
                                    <label class="form-check-label" for="send_email">E-mail</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" value="1" id="send_sms" name="send_sms"
                                           @checked($options['send_sms'])>
                                    <label class="form-check-label" for="send_sms">SMS (świadomie włącz)</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" value="1" id="check_ifirma" name="check_ifirma"
                                           @checked($options['check_ifirma'])>
                                    <label class="form-check-label" for="check_ifirma">Sprawdź status w iFirma przed wysyłką</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" value="1" id="attach_ifirma_pdf" name="attach_ifirma_pdf"
                                           @checked($options['attach_ifirma_pdf'])>
                                    <label class="form-check-label" for="attach_ifirma_pdf">Załącz PDF FV z iFirma (tylko z ID)</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" value="1" id="sms_companion_email" name="sms_companion_email"
                                           @checked($options['sms_companion_email'])>
                                    <label class="form-check-label" for="sms_companion_email">Przy SMS: wyślij też e-mail przed SMS</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" value="1" id="strip_sms_diacritics" name="strip_sms_diacritics"
                                           @checked($options['strip_sms_diacritics'])>
                                    <label class="form-check-label" for="strip_sms_diacritics">Bez polskich znaków w SMS</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="email_template" class="form-label small mb-1">Szablon e-mail</label>
                                    <select id="email_template" name="email_template" class="form-select form-select-sm">
                                        @foreach($templateLabels as $key => $label)
                                            <option value="{{ $key }}" @selected($options['email_template'] === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label for="sms_template" class="form-label small mb-1">Szablon SMS</label>
                                    <select id="sms_template" name="sms_template" class="form-select form-select-sm">
                                        @foreach($templateLabels as $key => $label)
                                            <option value="{{ $key }}" @selected($options['sms_template'] === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="alert alert-warning small mb-0 d-none" id="ifirmaRiskAlert">
                                    Wyłączono sprawdzanie iFirma — ryzyko nieaktualnego statusu płatności.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 small mb-2">
                    <span>Kandydaci: <strong id="countCandidates">{{ $counts['candidates'] }}</strong></span>
                    <span>Wykluczeni: <strong>{{ $counts['excluded'] }}</strong></span>
                    <span>Zaznaczone: <strong id="countSelected">0</strong></span>
                    <span>Planowane e-maile: <strong id="countEmails">0</strong></span>
                    <span>Planowane SMS: <strong id="countSms">0</strong></span>
                </div>

                <div class="card mb-3">
                    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                        <span>Kandydaci</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleAllCandidates">Zaznacz / odznacz widoczne</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th></th>
                                <th>Sprawa / FV</th>
                                <th>Klient</th>
                                <th class="text-end">Do zapłaty</th>
                                <th>Termin</th>
                                <th>Po terminie</th>
                                <th>Ostatni kontakt</th>
                                <th>iFirma</th>
                                <th>E-mail</th>
                                <th>Tel.</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($candidates as $row)
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                               class="form-check-input candidate-check"
                                               value="{{ $row['id'] }}"
                                               data-has-email="{{ $row['has_email'] ? '1' : '0' }}"
                                               data-has-phone="{{ $row['has_phone'] ? '1' : '0' }}"
                                               checked>
                                    </td>
                                    <td class="small">
                                        <a href="{{ $row['show_url'] }}" class="fw-semibold text-decoration-none">#{{ $row['id'] }}</a>
                                        <div class="text-muted">{{ $row['invoice_number'] ?: '—' }}</div>
                                    </td>
                                    <td class="small">{{ $row['client'] }}</td>
                                    <td class="text-end text-nowrap small">{{ $row['amount_remaining_formatted'] }}</td>
                                    <td class="small text-nowrap">{{ $row['due_date'] ?: '—' }}</td>
                                    <td class="small">{{ $row['days_overdue'] }} dni</td>
                                    <td class="small">{{ $row['last_contact_at'] }}</td>
                                    <td class="small">
                                        {{ $row['ifirma_status'] }}
                                        @if($row['ifirma_synced_at'])
                                            <div class="text-muted">{{ $row['ifirma_synced_at'] }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ $row['has_email'] ? 'tak' : 'nie' }}</td>
                                    <td class="small">{{ $row['has_phone'] ? 'tak' : 'nie' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-muted small p-3">Brak kwalifikujących się spraw na tej zakładce.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($excluded->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">Automatycznie wykluczeni ({{ $excluded->count() }})</div>
                        <div class="table-responsive" style="max-height: 18rem; overflow: auto;">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Sprawa</th>
                                    <th>Powód</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($excluded as $row)
                                    <tr>
                                        <td class="small">
                                            <a href="{{ route('accounting.collections.show', $row['case']) }}">#{{ $row['case']->id }}</a>
                                            <span class="text-muted">{{ $row['case']->invoice_number ?: '' }}</span>
                                        </td>
                                        <td class="small">{{ $row['reason_label'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-warning" id="openConfirmCampaignBtn" @disabled($candidates->isEmpty())>
                        Zleć wysyłkę…
                    </button>
                    <a href="{{ route('accounting.bank-imports.index') }}" class="btn btn-outline-secondary">Anuluj</a>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="confirmCampaignModal" tabindex="-1" aria-labelledby="confirmCampaignModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header text-bg-warning">
                    <h5 class="modal-title" id="confirmCampaignModalLabel">Potwierdź kampanię</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body small">
                    <ul class="mb-0" id="confirmCampaignSummary"></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-warning" id="confirmCampaignSubmitBtn">Zleć wysyłkę</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('paymentReminderCampaignForm');
            var selectedContainer = document.getElementById('selectedIdsContainer');
            var sendEmail = document.getElementById('send_email');
            var sendSms = document.getElementById('send_sms');
            var checkIfirma = document.getElementById('check_ifirma');
            var ifirmaRisk = document.getElementById('ifirmaRiskAlert');
            var countSelected = document.getElementById('countSelected');
            var countEmails = document.getElementById('countEmails');
            var countSms = document.getElementById('countSms');
            var openBtn = document.getElementById('openConfirmCampaignBtn');
            var submitBtn = document.getElementById('confirmCampaignSubmitBtn');
            var summaryEl = document.getElementById('confirmCampaignSummary');
            var modalEl = document.getElementById('confirmCampaignModal');
            var modal = modalEl && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
            var audience = @json($audience === 'vip' ? 'VIP' : 'Standardowe');
            var excludedCount = {{ (int) $counts['excluded'] }};

            function selectedChecks() {
                return Array.prototype.slice.call(document.querySelectorAll('.candidate-check:checked'));
            }

            function refreshCounts() {
                var selected = selectedChecks();
                var emailOn = !!(sendEmail && sendEmail.checked);
                var smsOn = !!(sendSms && sendSms.checked);
                var companion = !!(document.getElementById('sms_companion_email') && document.getElementById('sms_companion_email').checked);
                var emails = 0;
                var sms = 0;
                selected.forEach(function (el) {
                    var hasEmail = el.getAttribute('data-has-email') === '1';
                    var hasPhone = el.getAttribute('data-has-phone') === '1';
                    if ((emailOn || (smsOn && companion)) && hasEmail) emails++;
                    if (smsOn && hasPhone) sms++;
                });
                if (countSelected) countSelected.textContent = String(selected.length);
                if (countEmails) countEmails.textContent = String(emails);
                if (countSms) countSms.textContent = String(sms);
                if (openBtn) openBtn.disabled = selected.length === 0 || (!emailOn && !smsOn);
                if (ifirmaRisk) {
                    ifirmaRisk.classList.toggle('d-none', !!(checkIfirma && checkIfirma.checked));
                }
            }

            function syncHiddenIds() {
                if (!selectedContainer) return;
                selectedContainer.innerHTML = '';
                selectedChecks().forEach(function (el) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_ids[]';
                    input.value = el.value;
                    selectedContainer.appendChild(input);
                });
            }

            document.querySelectorAll('.candidate-check').forEach(function (el) {
                el.addEventListener('change', refreshCounts);
            });
            [sendEmail, sendSms, checkIfirma, document.getElementById('sms_companion_email')].forEach(function (el) {
                if (el) el.addEventListener('change', refreshCounts);
            });

            var toggleAll = document.getElementById('toggleAllCandidates');
            if (toggleAll) {
                toggleAll.addEventListener('click', function () {
                    var boxes = document.querySelectorAll('.candidate-check');
                    var allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
                    boxes.forEach(function (b) { b.checked = !allChecked; });
                    refreshCounts();
                });
            }

            if (openBtn && modal) {
                openBtn.addEventListener('click', function () {
                    var selected = selectedChecks().length;
                    var emailOn = !!(sendEmail && sendEmail.checked);
                    var smsOn = !!(sendSms && sendSms.checked);
                    summaryEl.innerHTML =
                        '<li>Typ: <strong>' + audience + '</strong></li>' +
                        '<li>Wybrane sprawy: <strong>' + selected + '</strong></li>' +
                        '<li>E-mail: <strong>' + (emailOn ? 'TAK' : 'NIE') + '</strong> · szablon: ' + (document.getElementById('email_template')?.value || '') + '</li>' +
                        '<li>SMS: <strong>' + (smsOn ? 'TAK' : 'NIE') + '</strong> · szablon: ' + (document.getElementById('sms_template')?.value || '') + '</li>' +
                        '<li>iFirma: <strong>' + (checkIfirma && checkIfirma.checked ? 'sprawdzaj' : 'WYŁĄCZONE') + '</strong></li>' +
                        '<li>PDF iFirma: <strong>' + (document.getElementById('attach_ifirma_pdf')?.checked ? 'TAK' : 'NIE') + '</strong></li>' +
                        '<li>Wykluczeni automatycznie: <strong>' + excludedCount + '</strong></li>' +
                        '<li class="mt-2">Ostateczna liczba wysłanych może być mniejsza po weryfikacji w kolejce.</li>';
                    document.getElementById('confirmCampaignSubmitBtn').textContent = 'Zleć wysyłkę ' + selected + ' spraw';
                    modal.show();
                });
            }

            if (submitBtn && form) {
                submitBtn.addEventListener('click', function () {
                    syncHiddenIds();
                    if (modal) modal.hide();
                    form.requestSubmit();
                });
            }

            refreshCounts();
        });
    </script>
</x-app-layout>
