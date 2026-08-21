{{-- Panel statusu operacyjnego na /form-orders/{id} — odświeżany AJAX po wystawieniu FV / PNEDU --}}
@php
    $op = $zamowienie->operational_status;
    $isCancelled = $zamowienie->isCancelled();
    $isLegacyClosed = $zamowienie->isLegacyHandled();
    $isTerminal = $isCancelled || $isLegacyClosed;
    $expected = (int) ($op['expected_count'] ?? 0);
    $provisioned = (int) ($op['provisioned_count'] ?? 0);
    $participantsOk = $expected > 0 && $provisioned === $expected;
    $invoiceOk = $zamowienie->isBillingComplete();
    $isUnprocessed = ! $isTerminal && (! $participantsOk || ! $invoiceOk);

    if ($isCancelled) {
        $headerClass = 'bg-secondary text-white';
        $stateBadgeClass = 'bg-danger';
        $stateBadgeLabel = 'Anulowane';
    } elseif ($isLegacyClosed) {
        $headerClass = 'bg-secondary text-white';
        $stateBadgeClass = 'bg-dark';
        $stateBadgeLabel = 'Zamknięte';
    } elseif ($isUnprocessed) {
        $headerClass = 'bg-warning text-dark';
        $stateBadgeClass = 'bg-danger';
        $stateBadgeLabel = 'Nieprzetworzone';
    } else {
        $headerClass = 'bg-success text-white';
        $stateBadgeClass = 'bg-light text-success';
        $stateBadgeLabel = 'Przetworzone';
    }

    if ($participantsOk) {
        $participantsIcon = 'bi-check-circle-fill text-success';
        $participantsTitle = 'Uczestnicy dodani';
        $participantsDetail = 'Na szkoleniu (PNEDU): '.$provisioned.'/'.$expected;
        $participantsDetailClass = 'text-muted';
    } elseif ($provisioned > 0) {
        $participantsIcon = 'bi-exclamation-circle-fill text-warning';
        $participantsTitle = 'Uczestnicy częściowo dodani';
        $participantsDetail = 'Na szkoleniu (PNEDU): '.$provisioned.'/'.$expected;
        $participantsDetailClass = 'text-warning';
    } else {
        $participantsIcon = 'bi-x-circle-fill text-danger';
        $participantsTitle = 'Uczestnicy nie dodani';
        $participantsDetail = $expected > 0
            ? 'Na szkoleniu (PNEDU): 0/'.$expected
            : 'Brak uczestnika z adresem e-mail.';
        $participantsDetailClass = 'text-danger';
    }

    if ($invoiceOk && $zamowienie->has_invoice) {
        $invoiceIcon = 'bi-check-circle-fill text-success';
        $invoiceTitle = 'Faktura wystawiona';
        $invoiceDetail = (string) $zamowienie->invoice_number;
        $invoiceDetailClass = 'text-muted';
    } elseif ($invoiceOk && $zamowienie->isInvoiceExempt()) {
        $invoiceIcon = 'bi-check-circle-fill text-success';
        $invoiceTitle = 'Faktura nie jest wymagana';
        $invoiceDetail = 'Oznaczone jako bezpłatny dostęp (bez FV).';
        $invoiceDetailClass = 'text-muted';
    } else {
        $invoiceIcon = 'bi-x-circle-fill text-danger';
        $invoiceTitle = 'Faktura nie wystawiona';
        $invoiceDetail = 'Brak numeru faktury.';
        $invoiceDetailClass = 'text-danger';
    }
@endphp

<div class="card mb-3">
    <div class="card-header {{ $headerClass }} py-2 d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <h6 class="mb-0">
            <i class="bi bi-clipboard-check"></i> STATUS ZAMÓWIENIA
        </h6>
        <span class="badge {{ $stateBadgeClass }} fs-6" title="{{ $op['label'] }}">
            {{ $stateBadgeLabel }}
        </span>
    </div>
    <div class="card-body py-3">
        <div class="row g-3">
            <div class="col-sm-6">
                <div class="d-flex align-items-start gap-2 h-100">
                    <i class="bi {{ $participantsIcon }} fs-5 mt-0" aria-hidden="true"></i>
                    <div>
                        <div class="fw-semibold">{{ $participantsTitle }}</div>
                        <div class="small {{ $participantsDetailClass }}">{{ $participantsDetail }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="d-flex align-items-start gap-2 h-100">
                    <i class="bi {{ $invoiceIcon }} fs-5 mt-0" aria-hidden="true"></i>
                    <div>
                        <div class="fw-semibold">{{ $invoiceTitle }}</div>
                        <div class="small {{ $invoiceDetailClass }}">
                            {{ $invoiceDetail }}
                            @if($zamowienie->has_invoice && $zamowienie->hasConfirmedKsef())
                                <span class="ms-1">· KSeF {{ $zamowienie->ksef_number }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $showOperationalLabel = ! in_array($op['label'], ['Przetworzone', 'Anulowane', 'Legacy — zamknięte'], true);
        @endphp
        @if($showOperationalLabel || $isCancelled || $isLegacyClosed)
            <div class="mt-3">
                @if($showOperationalLabel)
                    <span class="badge {{ $op['badge_class'] }}" title="Status operacyjny (uczestnicy + faktura)">
                        {{ $op['label'] }}
                    </span>
                @endif
                @if($isCancelled)
                    <span class="badge bg-secondary" title="{{ $zamowienie->cancelled_reason }}">
                        <i class="bi bi-x-circle"></i> Anulowano {{ $zamowienie->cancelled_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                    </span>
                @endif
                @if($isLegacyClosed)
                    <span class="badge bg-secondary" title="{{ $zamowienie->legacy_handled_reason }}">
                        <i class="bi bi-archive"></i> Legacy zamknięte
                    </span>
                @endif
            </div>
        @endif

        @if(!empty($op['warnings']))
            <div class="small mt-2 mb-0">
                @foreach($op['warnings'] as $warning)
                    <div class="text-danger"><i class="bi bi-exclamation-triangle"></i> {{ $warning }}</div>
                @endforeach
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mt-3">
            @if($isCancelled)
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
                @elseif(! $zamowienie->has_invoice)
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
    </div>
</div>
