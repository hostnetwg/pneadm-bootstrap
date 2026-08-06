<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Windykacja
            </h2>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('accounting.bank-imports.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-bank"></i> Import wyciągu
                </a>
                <a href="{{ route('accounting.debtors.index') }}" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-search"></i> Lookup faktury
                </a>
                <a href="{{ route('accounting.collections.settings.edit') }}" class="btn btn-outline-dark btn-sm">
                    <i class="bi bi-gear"></i> Ustawienia
                </a>
            </div>
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

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">Nie udało się zapisać danych:</div>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <a href="{{ route('accounting.collections.index', ['status' => 'active']) }}"
                       class="card h-100 text-decoration-none text-dark{{ $status === 'active' ? ' border-primary' : '' }}">
                        <div class="card-body">
                            <div class="small text-muted">Aktywne sprawy</div>
                            <div class="fs-4 fw-semibold">{{ $stats['active'] }}</div>
                            <div class="small text-primary mt-1">Pokaż niezamknięte</div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100 border-warning">
                        <div class="card-body">
                            <div class="small text-muted">VIP / lojalni z zaległością</div>
                            <div class="fs-4 fw-semibold">{{ $stats['vip'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Obietnice płatności</div>
                            <div class="fs-4 fw-semibold">{{ $stats['promised'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100 border-danger">
                        <div class="card-body">
                            <div class="small text-muted">Do kontaktu dziś</div>
                            <div class="fs-4 fw-semibold">{{ $stats['due_today'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-5">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Filtry listy spraw</div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('accounting.collections.index') }}" class="row g-2 align-items-end">
                                <div class="col-12">
                                    <label for="search" class="form-label small mb-1">Szukaj w sprawach</label>
                                    <input type="text" id="search" name="search" class="form-control form-control-sm"
                                           value="{{ $search }}" placeholder="FV, KSeF, ID, NIP, e-mail, nazwa">
                                </div>
                                <div class="col-6">
                                    <label for="status" class="form-label small mb-1">Status</label>
                                    <select id="status" name="status" class="form-select form-select-sm">
                                        <option value="active" @selected($status === 'active')>Niezamknięte</option>
                                        <option value="all" @selected($status === 'all')>Wszystkie</option>
                                        @foreach($statusLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label for="segment" class="form-label small mb-1">Segment</label>
                                    <select id="segment" name="segment" class="form-select form-select-sm">
                                        <option value="">Wszystkie</option>
                                        @foreach($segmentLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($segment === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-search"></i> Filtruj
                                    </button>
                                    @if($search !== '' || $status !== 'active' || $segment !== '')
                                        <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Utwórz sprawę</div>
                        <div class="card-body">
                            <div class="row g-3 align-items-start">
                                <div class="col-12 col-md-6">
                                    <label for="createInvoiceLookup" class="form-label small mb-1">
                                        <i class="bi bi-receipt"></i>
                                        Znajdź ID zamówienia po numerze faktury / KSeF
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="text"
                                               id="createInvoiceLookup"
                                               class="form-control"
                                               placeholder="np. 349/6/2026 lub 7392137630-20260724-…"
                                               autocomplete="off"
                                               maxlength="128">
                                        <button type="button" class="btn btn-outline-success" id="createInvoiceLookupBtn">
                                            <i class="bi bi-search"></i> Szukaj
                                        </button>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="createInvoiceLookupExact" checked>
                                        <label class="form-check-label small" for="createInvoiceLookupExact">
                                            Szukaj dokładnie wpisanego numeru
                                        </label>
                                    </div>
                                    <div id="createInvoiceLookupStatus" class="form-text mt-1 mb-0">
                                        Wpisz numer z iFirma, potem wstaw ID zamówienia obok.
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <form method="POST" action="{{ route('accounting.collections.store') }}" id="createDebtCaseForm">
                                        @csrf
                                        <label for="form_order_id" class="form-label small mb-1">ID zamówienia `form_orders`</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" min="1" id="form_order_id" name="form_order_id" class="form-control" required value="{{ old('form_order_id') }}">
                                            <button type="submit" class="btn btn-success" id="createDebtCaseSubmitBtn">
                                                <i class="bi bi-plus-circle"></i> Dodaj
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            Wymagana wystawiona FV. Status płatności potwierdzasz w iFirma; tutaj rejestrujemy działania i kontekst.
                                        </div>
                                    </form>
                                    <div class="mt-2">
                                        <a href="{{ route('accounting.debtors.index') }}" class="small">Pełny lookup faktury / historii klienta</a>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div id="createInvoiceLookupResults" class="d-none"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Sprawy windykacyjne</span>
                    <span class="small text-muted">Wyświetlanie {{ $cases->firstItem() ?? 0 }}-{{ $cases->lastItem() ?? 0 }} z {{ $cases->total() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sprawa</th>
                                <th>Zamówienie / faktura</th>
                                <th>Klient</th>
                                <th>Status</th>
                                <th>Segment</th>
                                <th>Opiekun</th>
                                <th>Termin</th>
                                <th>Następny krok</th>
                                <th class="text-end">Kwota</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                                @php($order = $case->formOrder)
                                <tr>
                                    <td>
                                        <a href="{{ route('accounting.collections.show', $case) }}" class="fw-semibold text-decoration-none">
                                            #{{ $case->id }}
                                        </a>
                                        @if($case->isVip())
                                            <span class="badge text-bg-warning ms-1" title="{{ $case->vip_reason ?: 'VIP / ważny klient' }}">VIP</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('form-orders.show', $order->id) }}" class="text-decoration-none">#{{ $order->id }}</a>
                                        <div class="small text-muted">FV: {{ $case->invoice_number ?: $order->invoice_number ?: '—' }}</div>
                                        @if($case->ksef_number || $order->ksef_number)
                                            <div class="small text-success">KSeF: {{ $case->ksef_number ?: $order->ksef_number }}</div>
                                        @endif
                                        @if($case->ifirma_payment_status)
                                            <div class="mt-1">
                                                <span class="badge {{ \App\Services\IfirmaInvoicePaymentStatusService::statusBadgeClass($case->ifirma_payment_status) }}">
                                                    iFirma: {{ $case->ifirmaPaymentStatusLabel() }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $order->recipient_name ?: $order->buyer_name ?: $order->orderer_name ?: '—' }}</div>
                                        <div class="small text-muted">{{ $order->orderer_email ?: $order->display_participant_email }}</div>
                                    </td>
                                    <td><span class="badge {{ $case->statusBadgeClass() }}">{{ $case->statusLabel() }}</span></td>
                                    <td>
                                        <span class="badge {{ $case->isVip() ? 'text-bg-warning' : 'text-bg-light border' }}">
                                            {{ $case->segmentLabel() }}
                                        </span>
                                        <div class="small text-muted"
                                             role="button"
                                             tabindex="0"
                                             data-bs-toggle="tooltip"
                                             data-bs-placement="top"
                                             data-bs-title="R = ryzyko (zaległości / przeterminowanie). L = lojalność / relacja (liczba i wartość powiązanych zamówień oraz opłaty online). Im wyższe L (≥60), tym VIP."
                                             title="R = ryzyko (zaległości / przeterminowanie). L = lojalność / relacja (liczba i wartość powiązanych zamówień oraz opłaty online). Im wyższe L (≥60), tym VIP.">
                                            R: {{ $case->risk_score }} / L: {{ $case->relationship_score }}
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $case->assignedTo?->name ?: '—' }}</div>
                                        @if($case->createdBy && $case->createdBy->id !== $case->assigned_to_id)
                                            <div class="small text-muted">utworzył: {{ $case->createdBy->name }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $case->due_date?->format('d.m.Y') ?: '—' }}</td>
                                    <td>{{ $case->next_action_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?: '—' }}</td>
                                    <td class="text-end">{{ number_format((float) ($case->amount_gross ?? $order->product_price ?? 0), 2, ',', ' ') }} zł</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        Brak spraw windykacyjnych dla wybranych filtrów.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $cases->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const input = document.getElementById('createInvoiceLookup');
            const exactCheckbox = document.getElementById('createInvoiceLookupExact');
            const searchBtn = document.getElementById('createInvoiceLookupBtn');
            const statusEl = document.getElementById('createInvoiceLookupStatus');
            const resultsEl = document.getElementById('createInvoiceLookupResults');
            const orderIdInput = document.getElementById('form_order_id');
            const lookupUrl = @json(route('accounting.debtors.lookup'));
            const collectionsStoreUrl = @json(route('accounting.collections.store'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (!input || !orderIdInput || !resultsEl || !statusEl) {
                return;
            }

            let debounceTimer = null;
            let activeRequest = null;

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const setStatus = (message, tone = 'muted') => {
                statusEl.className = `form-text mt-1 mb-0 text-${tone}`;
                statusEl.textContent = message;
            };

            const clearResults = () => {
                resultsEl.classList.add('d-none');
                resultsEl.innerHTML = '';
            };

            const useOrderId = (orderId) => {
                orderIdInput.value = String(orderId);
                orderIdInput.classList.add('border-success');
                orderIdInput.focus();
                orderIdInput.select();
                setStatus(`Wstawiono ID zamówienia #${orderId}. Możesz kliknąć „Dodaj”.`, 'success');
                setTimeout(() => orderIdInput.classList.remove('border-success'), 1800);
            };

            const actionButtonsHtml = (match) => {
                const debtCase = match.active_debt_case;
                if (debtCase) {
                    return `
                        <div class="d-flex flex-column gap-1">
                            <a href="${escapeHtml(debtCase.url)}" class="btn btn-sm btn-outline-danger">Otwórz sprawę</a>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary js-use-order-id"
                                    data-order-id="${escapeHtml(match.id)}">
                                Wstaw ID
                            </button>
                        </div>
                    `;
                }

                return `
                    <div class="d-flex flex-column gap-1">
                        <button type="button"
                                class="btn btn-sm btn-danger w-100 js-confirm-create-case"
                                data-order-id="${escapeHtml(match.id)}"
                                data-invoice="${escapeHtml(match.invoice_number || '')}"
                                data-product="${escapeHtml(match.product_name || '')}">
                            Utwórz sprawę
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary js-use-order-id"
                                data-order-id="${escapeHtml(match.id)}">
                            Wstaw ID
                        </button>
                    </div>
                `;
            };

            const renderMatches = (matches) => {
                if (!Array.isArray(matches) || matches.length === 0) {
                    clearResults();
                    setStatus('Brak zamówień z tym numerem faktury / KSeF.', 'warning');
                    return;
                }

                const rows = matches.map((match) => {
                    const invoice = match.invoice_number || '—';
                    const ksef = match.ksef_number
                        ? `<div class="small text-success">KSeF: ${escapeHtml(match.ksef_number)}</div>`
                        : '';
                    const buyer = match.buyer_name || match.recipient_name || '—';
                    const product = match.product_name || '—';
                    const debtCaseBadge = match.active_debt_case
                        ? `<div class="small text-danger mt-1">Sprawa #${escapeHtml(match.active_debt_case.id)} · ${escapeHtml(match.active_debt_case.status_label)}</div>`
                        : '';

                    return `
                        <div class="border rounded px-2 py-2 mb-2 bg-light">
                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                <div class="small">
                                    <div><strong>#${escapeHtml(match.id)}</strong> · FV: ${escapeHtml(invoice)}</div>
                                    ${ksef}
                                    <div class="text-muted">${escapeHtml(buyer)}</div>
                                    <div class="text-muted">${escapeHtml(product)}</div>
                                    ${debtCaseBadge}
                                </div>
                                ${actionButtonsHtml(match)}
                            </div>
                        </div>
                    `;
                }).join('');

                resultsEl.innerHTML = rows;
                resultsEl.classList.remove('d-none');
                setStatus(`Znaleziono ${matches.length}. Utwórz sprawę od razu albo wstaw ID.`, 'success');
            };

            const performLookup = async () => {
                const q = input.value.trim();
                if (q.length < 2) {
                    clearResults();
                    setStatus('Wpisz co najmniej 2 znaki numeru faktury lub KSeF.', 'muted');
                    return;
                }

                if (activeRequest) {
                    activeRequest.abort();
                }
                activeRequest = new AbortController();
                setStatus('Wyszukiwanie…', 'muted');

                try {
                    const matchMode = exactCheckbox.checked ? 'exact' : 'partial';
                    const response = await fetch(
                        `${lookupUrl}?q=${encodeURIComponent(q)}&match_mode=${matchMode}`,
                        {
                            headers: { 'Accept': 'application/json' },
                            signal: activeRequest.signal,
                        }
                    );

                    if (!response.ok) {
                        throw new Error('lookup failed');
                    }

                    const payload = await response.json();
                    renderMatches(payload.matches || []);
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    clearResults();
                    setStatus('Nie udało się pobrać wyników. Spróbuj ponownie.', 'danger');
                }
            };

            searchBtn?.addEventListener('click', performLookup);

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    performLookup();
                }
            });

            input.addEventListener('input', () => {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                const q = input.value.trim();
                if (q.length < 2) {
                    clearResults();
                    setStatus('Wpisz numer z iFirma, potem wstaw ID zamówienia poniżej.', 'muted');
                    return;
                }

                debounceTimer = setTimeout(performLookup, 350);
            });

            exactCheckbox?.addEventListener('change', () => {
                if (input.value.trim().length >= 2) {
                    performLookup();
                }
            });

            resultsEl.addEventListener('click', (event) => {
                const useBtn = event.target.closest('.js-use-order-id');
                if (useBtn) {
                    useOrderId(useBtn.getAttribute('data-order-id'));
                    return;
                }

                const createBtn = event.target.closest('.js-confirm-create-case');
                if (createBtn) {
                    openCreateCaseModal({
                        orderId: createBtn.getAttribute('data-order-id'),
                        invoice: createBtn.getAttribute('data-invoice') || '',
                        product: createBtn.getAttribute('data-product') || '',
                    });
                }
            });

            const createForm = document.getElementById('createDebtCaseForm');
            const confirmModalEl = document.getElementById('confirmCreateDebtCaseModal');
            const confirmForm = document.getElementById('confirmCreateDebtCaseForm');
            const confirmOrderIdInput = document.getElementById('confirmCreateDebtCaseOrderId');
            const confirmSummary = document.getElementById('confirmCreateDebtCaseSummary');
            let createCaseModal = null;

            const openCreateCaseModal = ({ orderId, invoice = '', product = '' }) => {
                if (!confirmModalEl || !confirmOrderIdInput || !confirmSummary || !window.bootstrap?.Modal) {
                    return;
                }
                confirmOrderIdInput.value = String(orderId || '');
                const invoiceLine = invoice ? `<div><strong>FV:</strong> ${escapeHtml(invoice)}</div>` : '';
                const productLine = product ? `<div class="text-muted">${escapeHtml(product)}</div>` : '';
                confirmSummary.innerHTML = `
                    <div><strong>Zamówienie:</strong> #${escapeHtml(orderId)}</div>
                    ${invoiceLine}
                    ${productLine}
                `;
                createCaseModal = window.bootstrap.Modal.getOrCreateInstance(confirmModalEl);
                createCaseModal.show();
            };

            createForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                const orderId = orderIdInput.value.trim();
                if (!orderId) {
                    orderIdInput.focus();
                    return;
                }
                openCreateCaseModal({ orderId });
            });

            if (window.bootstrap?.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                    window.bootstrap.Tooltip.getOrCreateInstance(el);
                });
            }
        })();
    </script>

    <div class="modal fade" id="confirmCreateDebtCaseModal" tabindex="-1" aria-labelledby="confirmCreateDebtCaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('accounting.collections.store') }}" id="confirmCreateDebtCaseForm">
                    @csrf
                    <input type="hidden" name="form_order_id" id="confirmCreateDebtCaseOrderId" value="">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="confirmCreateDebtCaseModalLabel">
                            <i class="bi bi-exclamation-octagon"></i> Utworzyć sprawę windykacyjną?
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Sprawa zostanie otwarta dla wskazanego zamówienia.</p>
                        <div id="confirmCreateDebtCaseSummary" class="border rounded p-2 bg-light small mb-3"></div>
                        <div class="alert alert-warning small mb-0">
                            Wymagana wystawiona FV. Twórz sprawę tylko gdy faktura wymaga ponaglenia lub weryfikacji płatności.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-plus-circle"></i> Utwórz sprawę
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
