<div class="modal fade" id="trainerSettlementModal" tabindex="-1" aria-labelledby="trainerSettlementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trainerSettlementModalLabel">Rozliczenie trenera</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="trainerSettlementCourseInfo"></p>
                <form id="trainerSettlementForm" class="needs-validation" novalidate>
                    <input type="hidden" id="tsCourseId" name="course_id">

                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" id="tsUseExistingInvoice">
                        <label class="form-check-label" for="tsUseExistingInvoice">
                            Przypnij do istniejącej faktury zbiorczej tego trenera
                        </label>
                    </div>

                    <div id="tsExistingInvoiceBlock" class="mb-3 d-none">
                        <label for="tsTrainerInvoiceId" class="form-label">Istniejąca faktura</label>
                        <select class="form-select" id="tsTrainerInvoiceId" name="trainer_invoice_id">
                            <option value="">— wybierz fakturę —</option>
                        </select>
                    </div>

                    <div id="tsNewInvoiceBlock">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label for="tsInvoiceNumber" class="form-label">Numer faktury <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="tsInvoiceNumber" name="invoice_number" maxlength="64">
                            </div>
                            <div class="col-md-6">
                                <label for="tsKsefNumber" class="form-label">Numer KSeF</label>
                                <input type="text" class="form-control" id="tsKsefNumber" name="ksef_number" maxlength="128">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="tsInvoiceDate" class="form-label">Data faktury</label>
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
                            <label for="tsPaymentStatus" class="form-label">Status płatności faktury</label>
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
                        <label for="tsInvoiceNotes" class="form-label">Notatki do faktury</label>
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
    const invoiceSelect = document.getElementById('tsTrainerInvoiceId');
    const removeBtn = document.getElementById('tsRemoveBtn');
    const paymentStatus = document.getElementById('tsPaymentStatus');
    const paidAt = document.getElementById('tsPaidAt');

    let currentCourseId = null;
    let currentInstructorId = null;
    let hasSettlement = false;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function toggleInvoiceMode() {
        const use = useExisting.checked;
        existingBlock.classList.toggle('d-none', !use);
        newBlock.classList.toggle('d-none', use);
        document.getElementById('tsInvoiceNumber').required = !use;
    }

    useExisting.addEventListener('change', toggleInvoiceMode);

    paymentStatus.addEventListener('change', function() {
        if (paymentStatus.value === 'paid' && !paidAt.value) {
            paidAt.value = new Date().toISOString().slice(0, 10);
        }
    });

    function loadInstructorInvoices(instructorId, selectedId) {
        return fetch(`/instructors/${instructorId}/trainer-invoices`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            invoiceSelect.innerHTML = '<option value="">— wybierz fakturę —</option>';
            if (data.success && data.invoices) {
                data.invoices.forEach(inv => {
                    const opt = document.createElement('option');
                    opt.value = inv.id;
                    const status = inv.payment_status === 'paid' ? 'opł.' : 'nieopł.';
                    opt.textContent = `${inv.invoice_number} (${status}, ${inv.items_count} szk., ${parseFloat(inv.total_amount).toFixed(2)} zł)`;
                    if (selectedId && String(selectedId) === String(inv.id)) {
                        opt.selected = true;
                    }
                    invoiceSelect.appendChild(opt);
                });
            }
        });
    }

    function fillFormFromSettlement(settlement) {
        const inv = settlement.invoice;
        document.getElementById('tsAmountGross').value = settlement.amount_gross;
        document.getElementById('tsAmountNet').value = settlement.amount_net || '';
        document.getElementById('tsInvoiceNumber').value = inv.invoice_number;
        document.getElementById('tsKsefNumber').value = inv.ksef_number || '';
        document.getElementById('tsInvoiceDate').value = inv.invoice_date || '';
        paymentStatus.value = inv.payment_status;
        paidAt.value = inv.paid_at ? inv.paid_at.slice(0, 10) : '';
        document.getElementById('tsInvoiceNotes').value = inv.notes || '';
        useExisting.checked = false;
        toggleInvoiceMode();
        loadInstructorInvoices(currentInstructorId, inv.id).then(() => {
            useExisting.checked = true;
            toggleInvoiceMode();
            invoiceSelect.value = inv.id;
            document.getElementById('tsInvoiceNotes').value = inv.notes || '';
        });
    }

    function resetForm() {
        form.reset();
        useExisting.checked = false;
        toggleInvoiceMode();
        removeBtn.classList.add('d-none');
        hasSettlement = false;
    }

    document.querySelectorAll('.trainer-settlement-open').forEach(btn => {
        btn.addEventListener('click', function() {
            currentCourseId = this.dataset.courseId;
            currentInstructorId = this.dataset.instructorId;
            const instructorName = this.dataset.instructorName;
            courseInfo.textContent = `Szkolenie #${currentCourseId} · ${instructorName}`;
            document.getElementById('tsCourseId').value = currentCourseId;
            resetForm();

            fetch(`/courses/${currentCourseId}/trainer-settlement`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.settlement) {
                    hasSettlement = true;
                    removeBtn.classList.remove('d-none');
                    fillFormFromSettlement(data.settlement);
                }
                loadInstructorInvoices(currentInstructorId);
                modal.show();
            })
            .catch(() => {
                loadInstructorInvoices(currentInstructorId);
                modal.show();
            });
        });
    });

    document.getElementById('tsSaveBtn').addEventListener('click', function() {
        const payload = {
            amount_gross: document.getElementById('tsAmountGross').value,
            amount_net: document.getElementById('tsAmountNet').value || null,
            payment_status: paymentStatus.value,
            paid_at: paidAt.value || null,
            invoice_notes: document.getElementById('tsInvoiceNotes').value || null,
            ksef_number: document.getElementById('tsKsefNumber').value || null,
            invoice_date: document.getElementById('tsInvoiceDate').value || null,
        };

        if (useExisting.checked && invoiceSelect.value) {
            payload.trainer_invoice_id = parseInt(invoiceSelect.value, 10);
        } else {
            payload.invoice_number = document.getElementById('tsInvoiceNumber').value;
        }

        fetch(`/courses/${currentCourseId}/trainer-settlement`, {
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
        if (!confirm('Usunąć powiązanie faktury z tym szkoleniem? (Faktura i inne pozycje pozostaną.)')) return;
        fetch(`/courses/${currentCourseId}/trainer-settlement`, {
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
