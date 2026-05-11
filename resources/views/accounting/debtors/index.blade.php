<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            Dłużnicy (do weryfikacji)
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <div class="card mb-3">
                <div class="card-body">
                    <label for="invoiceLookup" class="form-label fw-semibold mb-2">Numer faktury</label>
                    <input
                        id="invoiceLookup"
                        type="text"
                        class="form-control"
                        placeholder="Wpisz numer faktury (np. FV/12/05/2026)"
                        autocomplete="off"
                    >
                    <div class="form-text mt-2">
                        Wyszukiwanie działa na żywo, bez przeładowania strony.
                    </div>
                </div>
            </div>

            <div id="debtorsStatus" class="alert alert-info mb-3">
                Wpisz co najmniej 2 znaki numeru faktury.
            </div>

            <div id="debtorsResults" class="d-none">
                <div class="alert alert-warning">
                    <strong>Uwaga:</strong> dla faktur odroczonych system nie przechowuje statusu opłacenia.
                    Przed wysłaniem ponaglenia zawsze sprawdź opłacenie w iFirma.
                </div>

                <div class="card mb-3">
                    <div class="card-header fw-semibold">Dopasowane faktury</div>
                    <div class="card-body">
                        <div id="matchesContainer" class="small text-muted"></div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-xl-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">Zamawiający i uczestnik</div>
                            <div class="card-body" id="ordererParticipantCard"></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">Nabywca i odbiorca</div>
                            <div class="card-body" id="buyerRecipientCard"></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header fw-semibold">Podsumowanie historii klienta</div>
                    <div class="card-body" id="historyStats"></div>
                </div>

                <div class="card">
                    <div class="card-header fw-semibold">Historia zamówień powiązanych</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Zamówienie</th>
                                        <th>Faktura</th>
                                        <th>Data faktury</th>
                                        <th>Termin płatności</th>
                                        <th>Po terminie</th>
                                        <th>Szkolenie</th>
                                        <th class="text-end">Kwota</th>
                                        <th>Status płatności</th>
                                    </tr>
                                </thead>
                                <tbody id="historyRows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const input = document.getElementById('invoiceLookup');
            const statusEl = document.getElementById('debtorsStatus');
            const resultsEl = document.getElementById('debtorsResults');
            const matchesContainer = document.getElementById('matchesContainer');
            const ordererParticipantCard = document.getElementById('ordererParticipantCard');
            const buyerRecipientCard = document.getElementById('buyerRecipientCard');
            const historyStats = document.getElementById('historyStats');
            const historyRows = document.getElementById('historyRows');

            let debounceTimer = null;
            let activeRequest = null;

            const escapeHtml = (value) => {
                if (value === null || value === undefined || value === '') {
                    return '—';
                }
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const money = (value) => {
                const numeric = Number(value || 0);
                return `${numeric.toFixed(2)} zł`;
            };

                const overdueLabel = (record) => {
                    if (record.payment_mode === 'online_gateway') {
                        return '—';
                    }
                    return `${record.overdue_days || 0} dni`;
                };

            const setStatus = (message, level = 'info') => {
                statusEl.className = `alert alert-${level} mb-3`;
                statusEl.textContent = message;
            };

            const clearResults = () => {
                resultsEl.classList.add('d-none');
                matchesContainer.innerHTML = '';
                ordererParticipantCard.innerHTML = '';
                buyerRecipientCard.innerHTML = '';
                historyStats.innerHTML = '';
                historyRows.innerHTML = '';
            };

            const renderPayload = (payload) => {
                if (!payload.selected) {
                    clearResults();
                    setStatus('Brak dopasowania dla podanego numeru faktury.', 'warning');
                    return;
                }

                setStatus('Znaleziono dopasowanie. Zweryfikuj dane przed wysłaniem ponaglenia.', 'success');
                resultsEl.classList.remove('d-none');

                matchesContainer.innerHTML = payload.matches
                    .map((match) => {
                        return `<span class="badge text-bg-light border me-2 mb-2">#${escapeHtml(match.id)} | ${escapeHtml(match.invoice_number)} | ${escapeHtml(match.product_name)}</span>`;
                    })
                    .join('');

                const selected = payload.selected;
                ordererParticipantCard.innerHTML = `
                    <p class="mb-1"><strong>Faktura:</strong> ${escapeHtml(selected.invoice_number)}</p>
                    <p class="mb-1"><strong>Data faktury:</strong> ${escapeHtml(selected.invoice_date)}</p>
                    <p class="mb-1"><strong>Termin płatności:</strong> ${escapeHtml(selected.payment_due_date)} (${escapeHtml(selected.invoice_payment_delay)} dni)</p>
                    <p class="mb-2"><strong>Po terminie:</strong> ${escapeHtml(overdueLabel(selected))}</p>
                    <p class="mb-1"><strong>Tryb:</strong> ${escapeHtml(selected.payment_mode_label)}</p>
                    <p class="mb-2"><strong>Status:</strong> ${escapeHtml(selected.payment_status_hint)}</p>
                    <hr>
                    <p class="mb-1"><strong>Zamawiający:</strong> ${escapeHtml(selected.orderer.name)}</p>
                    <p class="mb-1"><strong>E-mail:</strong> ${escapeHtml(selected.orderer.email)}</p>
                    <p class="mb-1"><strong>Telefon:</strong> ${escapeHtml(selected.orderer.phone)}</p>
                    <p class="mb-2"><strong>Adres:</strong> ${escapeHtml(selected.orderer.address)}, ${escapeHtml(selected.orderer.postal_code)} ${escapeHtml(selected.orderer.city)}</p>
                    <hr>
                    <p class="mb-1"><strong>Uczestnik:</strong> ${escapeHtml(selected.participant.name)}</p>
                    <p class="mb-0"><strong>E-mail uczestnika:</strong> ${escapeHtml(selected.participant.email)}</p>
                `;

                buyerRecipientCard.innerHTML = `
                    <p class="mb-1"><strong>Nabywca:</strong> ${escapeHtml(selected.buyer.name)}</p>
                    <p class="mb-1"><strong>NIP nabywcy:</strong> ${escapeHtml(selected.buyer.nip)}</p>
                    <p class="mb-2"><strong>Adres nabywcy:</strong> ${escapeHtml(selected.buyer.address)}, ${escapeHtml(selected.buyer.postal_code)} ${escapeHtml(selected.buyer.city)}</p>
                    <hr>
                    <p class="mb-1"><strong>Odbiorca:</strong> ${escapeHtml(selected.recipient.name)}</p>
                    <p class="mb-1"><strong>NIP odbiorcy:</strong> ${escapeHtml(selected.recipient.nip)}</p>
                    <p class="mb-0"><strong>Adres odbiorcy:</strong> ${escapeHtml(selected.recipient.address)}, ${escapeHtml(selected.recipient.postal_code)} ${escapeHtml(selected.recipient.city)}</p>
                `;

                const stats = payload.history.stats;
                const identity = payload.history.identity;
                historyStats.innerHTML = `
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-muted">Liczba zamówień</div>
                                <div class="fs-5 fw-semibold">${escapeHtml(stats.total_orders)}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-muted">Łączna wartość</div>
                                <div class="fs-5 fw-semibold">${money(stats.total_value)}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-muted">Klucz identyfikacji</div>
                                <div class="small">
                                    Odbiorca NIP: <strong>${escapeHtml(identity.recipient_nip)}</strong><br>
                                    Nabywca NIP: <strong>${escapeHtml(identity.buyer_nip)}</strong><br>
                                    E-maile: <strong>${escapeHtml((identity.emails || []).join(', '))}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 small">
                        <span class="badge text-bg-secondary me-1 mb-1">Odroczone: ${escapeHtml(stats.deferred_invoice_orders)}</span>
                        <span class="badge text-bg-info me-1 mb-1">Online: ${escapeHtml(stats.online_gateway_orders)}</span>
                        <span class="badge text-bg-success me-1 mb-1">Online opłacone: ${escapeHtml(stats.online_paid_orders)}</span>
                        <span class="badge text-bg-warning me-1 mb-1">Online oczekujące: ${escapeHtml(stats.online_pending_orders)}</span>
                        <span class="badge text-bg-danger me-1 mb-1">Online nieudane/anulowane: ${escapeHtml(stats.online_failed_or_cancelled_orders)}</span>
                    </div>
                `;

                historyRows.innerHTML = (payload.history.orders || []).map((order) => {
                    return `
                        <tr>
                            <td>#${escapeHtml(order.id)}</td>
                            <td>${escapeHtml(order.invoice_number)}</td>
                            <td>${escapeHtml(order.invoice_date)}</td>
                            <td>${escapeHtml(order.payment_due_date)}</td>
                            <td>${escapeHtml(overdueLabel(order))}</td>
                            <td>${escapeHtml(order.product_name)}</td>
                            <td class="text-end">${money(order.product_price)}</td>
                            <td>
                                <div>${escapeHtml(order.payment_mode_label)}</div>
                                <div class="small text-muted">${escapeHtml(order.payment_status_hint)}</div>
                            </td>
                        </tr>
                    `;
                }).join('');
            };

            const performLookup = async (q) => {
                if (activeRequest) {
                    activeRequest.abort();
                }

                activeRequest = new AbortController();
                setStatus('Wyszukiwanie...', 'info');

                try {
                    const response = await fetch(`{{ route('accounting.debtors.lookup') }}?q=${encodeURIComponent(q)}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                        signal: activeRequest.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Błąd odpowiedzi serwera');
                    }

                    const payload = await response.json();
                    renderPayload(payload);
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    clearResults();
                    setStatus('Nie udało się pobrać danych. Spróbuj ponownie.', 'danger');
                }
            };

            input.addEventListener('input', () => {
                const q = input.value.trim();

                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                if (q.length < 2) {
                    clearResults();
                    setStatus('Wpisz co najmniej 2 znaki numeru faktury.', 'info');
                    return;
                }

                debounceTimer = setTimeout(() => {
                    performLookup(q);
                }, 300);
            });
        })();
    </script>
</x-app-layout>
