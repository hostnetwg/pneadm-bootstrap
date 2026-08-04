{{-- Panel statusu operacyjnego na /form-orders/{id} — odświeżany AJAX po wystawieniu FV --}}
<div class="small text-muted fw-semibold mb-2">
    <i class="bi bi-clipboard-check"></i> Status operacyjny
</div>
@include('form-orders.partials.operational-status', [
    'zamowienie' => $zamowienie,
    'hide_invoice_badge' => true,
])
<div class="d-flex flex-wrap gap-2 mt-2">
    @if($zamowienie->cancelled_at)
        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#restoreOrderModal">
            <i class="bi bi-arrow-counterclockwise"></i> Przywróć zamówienie
        </button>
    @else
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
            <i class="bi bi-x-circle"></i> Anuluj zamówienie
        </button>
        @if($zamowienie->isInvoiceExempt())
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#clearInvoiceExemptModal">
                <i class="bi bi-receipt-cutoff"></i> Cofnij „bez FV”
            </button>
        @elseif(!$zamowienie->has_invoice)
            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#invoiceExemptModal">
                <i class="bi bi-gift"></i> Bezpłatny dostęp — bez FV
            </button>
        @endif
    @endif
</div>
@if($zamowienie->isInvoiceExempt())
    <p class="small text-muted mb-0 mt-2">
        <i class="bi bi-info-circle"></i>
        Oznaczone jako bez faktury
        {{ $zamowienie->invoice_exempt_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
        @if($zamowienie->invoice_exempt_reason)
            — {{ $zamowienie->invoice_exempt_reason }}
        @endif
    </p>
@endif
