@php
    $op = $zamowienie->operational_status;
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <span class="badge {{ $op['badge_class'] }}" title="Status operacyjny (uczestnicy + faktura)">
        {{ $op['label'] }}
    </span>
    @if($op['expected_count'] > 0)
        <span class="badge bg-light text-dark border" title="Uczestnicy z dostępem na szkoleniu / oczekiwani">
            <i class="bi bi-people"></i> {{ $op['provisioned_count'] }}/{{ $op['expected_count'] }}
        </span>
    @endif
    @if($zamowienie->isLegacyHandled())
        <span class="badge bg-secondary" title="{{ $zamowienie->legacy_handled_reason }}">
            <i class="bi bi-archive"></i> Legacy zamknięte
        </span>
    @elseif($zamowienie->isInvoiceExempt())
        <span class="badge bg-info text-dark" title="{{ $zamowienie->invoice_exempt_reason }}">
            <i class="bi bi-gift"></i> Bez FV
        </span>
    @elseif(empty($hide_invoice_badge) && $zamowienie->has_invoice)
        <span class="badge bg-info text-dark" title="Numer faktury: {{ $zamowienie->invoice_number }}">
            <i class="bi bi-receipt"></i> FV {{ $zamowienie->invoice_number }}
        </span>
    @endif
    @if($zamowienie->cancelled_at)
        <span class="badge bg-secondary" title="{{ $zamowienie->cancelled_reason }}">
            <i class="bi bi-x-circle"></i> Anulowano {{ $zamowienie->cancelled_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
        </span>
    @endif
</div>
@if(!empty($op['warnings']))
    <div class="small mb-2">
        @foreach($op['warnings'] as $warning)
            <div class="text-danger"><i class="bi bi-exclamation-triangle"></i> {{ $warning }}</div>
        @endforeach
    </div>
@endif
