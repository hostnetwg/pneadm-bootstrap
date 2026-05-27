<div class="modal fade" id="trainerSettlementModal" tabindex="-1" aria-labelledby="trainerSettlementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trainerSettlementModalLabel">Rozliczenie instruktora</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="trainerSettlementCourseInfo"></p>
                <form id="trainerSettlementForm" class="needs-validation" novalidate>
                    <input type="hidden" id="tsCourseId" name="course_id">

                    <div class="mb-3">
                        <label class="form-label">Rodzaj rozliczenia</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="settlement_type" id="tsSettlementTypeInvoice" value="invoice" checked>
                                <label class="form-check-label" for="tsSettlementTypeInvoice">Faktura</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="settlement_type" id="tsSettlementTypeMandate" value="mandate">
                                <label class="form-check-label" for="tsSettlementTypeMandate">Umowa zlecenie</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" id="tsUseExistingInvoice">
                        <label class="form-check-label" for="tsUseExistingInvoice" id="tsUseExistingLabel">
                            Przypnij do istniejącego rozliczenia zbiorczego tego instruktora
                        </label>
                    </div>

                    <div id="tsExistingInvoiceBlock" class="mb-3 d-none">
                        <label for="tsInstructorInvoiceId" class="form-label" id="tsExistingSelectLabel">Istniejące rozliczenie</label>
                        <select class="form-select" id="tsInstructorInvoiceId" name="instructor_invoice_id">
                            <option value="">— wybierz rozliczenie —</option>
                        </select>
                    </div>

                    <div id="tsNewInvoiceBlock">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label for="tsInvoiceNumber" class="form-label" id="tsInvoiceNumberLabel">Numer faktury <span class="text-danger ts-invoice-required">*</span></label>
                                <input type="text" class="form-control" id="tsInvoiceNumber" name="invoice_number" maxlength="64">
                                <div class="form-text d-none" id="tsInvoiceNumberHint">Opcjonalnie — pusty numer wygeneruje skrót UZ/rok/…</div>
                            </div>
                            <div class="col-md-6" id="tsKsefBlock">
                                <label for="tsKsefNumber" class="form-label">Numer KSeF</label>
                                <input type="text" class="form-control" id="tsKsefNumber" name="ksef_number" maxlength="128">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="tsInvoiceDate" class="form-label">Data dokumentu</label>
                            <input type="date" class="form-control" id="tsInvoiceDate" name="invoice_date">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="tsAmountGross" class="form-label">Kwota za to szkolenie (brutto) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="tsAmountGross" name="amount_gross" step="0.01" min="0" required>
                                <span class="input-group-text">zł</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="tsAmountNet" class="form-label">Kwota netto (opcjonalnie)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="tsAmountNet" name="amount_net" step="0.01" min="0">
                                <span class="input-group-text">zł</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="tsPaymentStatus" class="form-label">Status płatności</label>
                            <select class="form-select" id="tsPaymentStatus" name="payment_status">
                                <option value="unpaid">Nieopłacona</option>
                                <option value="paid">Opłacona</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tsPaidAt" class="form-label">Data zapłaty</label>
                            <input type="date" class="form-control" id="tsPaidAt" name="paid_at">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tsInvoiceNotes" class="form-label">Notatki</label>
                        <textarea class="form-control" id="tsInvoiceNotes" name="invoice_notes" rows="2" maxlength="2000"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger d-none" id="tsRemoveBtn">Usuń powiązanie ze szkoleniem</button>
                <div class="ms-auto">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-primary" id="tsSaveBtn">Zapisz</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('trainerSettlementModal');
    if (!modalEl) return;

    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('trainerSettlementForm');
    const courseInfo = document.getElementById('trainerSettlementCourseInfo');
    const useExisting = document.getElementById('tsUseExistingInvoice');
    const existingBlock = document.getElementById('tsExistingInvoiceBlock');
    const newBlock = document.getElementById('tsNewInvoiceBlock');
    const invoiceSelect = document.getElementById('tsInstructorInvoiceId');
    const removeBtn = document.getElementById('tsRemoveBtn');
    const paymentStatus = document.getElementById('tsPaymentStatus');
    const paidAt = document.getElementById('tsPaidAt');

    let currentCourseId = null;
    let currentInstructorId = null;
    let hasSettlement = false;
    let currentInstructorInvoiceId = null;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const typeInvoice = document.getElementById('tsSettlementTypeInvoice');
    const typeMandate = document.getElementById('tsSettlementTypeMandate');
    const ksefBlock = document.getElementById('tsKsefBlock');
    const invoiceNumberLabel = document.getElementById('tsInvoiceNumberLabel');
    const invoiceNumberHint = document.getElementById('tsInvoiceNumberHint');
    const invoiceNumberInput = document.getElementById('tsInvoiceNumber');

    function getSettlementType() {
        return typeMandate.checked ? 'mandate' : 'invoice';
    }

    function applySettlementTypeLabels() {
        const isMandate = getSettlementType() === 'mandate';
        ksefBlock.classList.toggle('d-none', isMandate);
        invoiceNumberHint.classList.toggle('d-none', !isMandate);
        document.querySelectorAll('.ts-invoice-required').forEach(el => el.classList.toggle('d-none', isMandate));
        invoiceNumberLabel.childNodes.forEach(n => {
            if (n.nodeType === Node.TEXT_NODE) {
                n.textContent = isMandate ? 'Numer umowy ' : 'Numer faktury ';
            }
        });
    }

    function syncSettlementTypeUi() {
        applySettlementTypeLabels();
        toggleInvoiceMode();
        if (currentInstructorId && useExisting.checked) {
            const selected = invoiceSelect.value || currentInstructorInvoiceId || null;
            loadInstructorInvoices(currentInstructorId, selected);
        }
    }

    typeInvoice.addEventListener('change', syncSettlementTypeUi);
    typeMandate.addEventListener('change', syncSettlementTypeUi);

    function toggleInvoiceMode() {
        const use = useExisting.checked;
        existingBlock.classList.toggle('d-none', !use);
        newBlock.classList.toggle('d-none', use);
        invoiceNumberInput.required = !use && getSettlementType() === 'invoice';
    }

    useExisting.addEventListener('change', toggleInvoiceMode);

    paymentStatus.addEventListener('change', function() {
        if (paymentStatus.value === 'paid' && !paidAt.value) {
            paidAt.value = new Date().toISOString().slice(0, 10);
        }
    });

    function loadInstructorInvoices(instructorId, selectedId) {
        const type = getSettlementType();
        return fetch(`/instructors/${instructorId}/instructor-invoices?settlement_type=${encodeURIComponent(type)}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            invoiceSelect.innerHTML = '<option value="">— wybierz rozliczenie —</option>';
            if (data.success && data.invoices) {
                data.invoices.forEach(inv => {
                    const opt = document.createElement('option');
                    opt.value = inv.id;
                    const status = inv.payment_status === 'paid' ? 'opł.' : 'nieopł.';
                    const typeLabel = inv.settlement_type === 'mandate' ? 'UZ' : 'FV';
                    opt.textContent = `[${typeLabel}] ${inv.invoice_number} (${status}, ${inv.items_count} szk., ${parseFloat(inv.total_amount).toFixed(2)} zł)`;
                    if (selectedId && String(selectedId) === String(inv.id)) {
                        opt.selected = true;
                    }
                    invoiceSelect.appendChild(opt);
                });
            }
        });
    }

    function fillHeaderFieldsFromInvoice(inv) {
        document.getElementById('tsInvoiceNumber').value = inv.invoice_number || '';
        document.getElementById('tsKsefNumber').value = inv.ksef_number || '';
        document.getElementById('tsInvoiceDate').value = inv.invoice_date || '';
        document.getElementById('tsInvoiceNotes').value = inv.notes || '';
    }

    function fillFormFromSettlement(settlement) {
        const inv = settlement.invoice;
        currentInstructorInvoiceId = inv.id;
        const itemsCount = inv.items_count || 1;
        const isConsolidated = itemsCount > 1;

        if (inv.settlement_type === 'mandate') {
            typeMandate.checked = true;
        } else {
            typeInvoice.checked = true;
        }
        applySettlementTypeLabels();

        document.getElementById('tsAmountGross').value = settlement.amount_gross;
        document.getElementById('tsAmountNet').value = settlement.amount_net || '';
        paymentStatus.value = inv.payment_status;
        paidAt.value = inv.paid_at ? inv.paid_at.slice(0, 10) : '';

        if (isConsolidated) {
            useExisting.checked = true;
            toggleInvoiceMode();
            loadInstructorInvoices(currentInstructorId, inv.id).then(() => {
                invoiceSelect.value = String(inv.id);
            });
        } else {
            useExisting.checked = false;
            fillHeaderFieldsFromInvoice(inv);
            toggleInvoiceMode();
        }
    }

    function resetForm() {
        form.reset();
        typeInvoice.checked = true;
        useExisting.checked = false;
        syncSettlementTypeUi();
        removeBtn.classList.add('d-none');
        hasSettlement = false;
        currentInstructorInvoiceId = null;
    }

    document.querySelectorAll('.instructor-settlement-open').forEach(btn => {
        btn.addEventListener('click', function() {
            currentCourseId = this.dataset.courseId;
            currentInstructorId = this.dataset.instructorId;
            const instructorName = this.dataset.instructorName;
            courseInfo.textContent = `Szkolenie #${currentCourseId} · ${instructorName}`;
            document.getElementById('tsCourseId').value = currentCourseId;
            resetForm();

            fetch(`/courses/${currentCourseId}/instructor-settlement`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.default_settlement_type === 'mandate') {
                    typeMandate.checked = true;
                } else {
                    typeInvoice.checked = true;
                }
                syncSettlementTypeUi();
                if (data.settlement) {
                    hasSettlement = true;
                    removeBtn.classList.remove('d-none');
                    fillFormFromSettlement(data.settlement);
                } else {
                    loadInstructorInvoices(currentInstructorId);
                }
                modal.show();
            })
            .catch(() => {
                if (currentInstructorId) {
                    loadInstructorInvoices(currentInstructorId);
                }
                modal.show();
            });
        });
    });

    document.getElementById('tsSaveBtn').addEventListener('click', function() {
        const payload = {
            settlement_type: getSettlementType(),
            amount_gross: document.getElementById('tsAmountGross').value,
            amount_net: document.getElementById('tsAmountNet').value || null,
            payment_status: paymentStatus.value,
            paid_at: paidAt.value || null,
            invoice_notes: document.getElementById('tsInvoiceNotes').value || null,
            ksef_number: document.getElementById('tsKsefNumber').value || null,
            invoice_date: document.getElementById('tsInvoiceDate').value || null,
        };

        if (useExisting.checked && invoiceSelect.value) {
            payload.instructor_invoice_id = parseInt(invoiceSelect.value, 10);
        } else if (currentInstructorInvoiceId) {
            payload.instructor_invoice_id = currentInstructorInvoiceId;
        } else if (invoiceNumberInput.value.trim() !== '') {
            payload.invoice_number = invoiceNumberInput.value.trim();
        }

        fetch(`/courses/${currentCourseId}/instructor-settlement`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
        .then(({ ok, data }) => {
            if (ok && data.success) {
                modal.hide();
                window.location.reload();
            } else {
                let msg = data.message || 'Błąd zapisu';
                if (data.errors) {
                    msg += '\n' + Object.values(data.errors).flat().join('\n');
                }
                alert(msg);
            }
        })
        .catch(() => alert('Błąd połączenia.'));
    });

    removeBtn.addEventListener('click', function() {
        if (!confirm('Usunąć powiązanie rozliczenia z tym szkoleniem? (Dokument i inne pozycje pozostaną.)')) return;
        fetch(`/courses/${currentCourseId}/instructor-settlement`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                modal.hide();
                window.location.reload();
            } else {
                alert(data.message || 'Błąd usuwania');
            }
        });
    });
});
</script>
@endpush
