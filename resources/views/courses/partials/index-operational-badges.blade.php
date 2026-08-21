@php
    $ordersNeedingParticipants = (int) ($ordersNeedingParticipants ?? 0);
    $ordersNeedingInvoice = (int) ($ordersNeedingInvoice ?? 0);
    $courseId = (int) ($courseId ?? 0);
@endphp
@if($ordersNeedingParticipants > 0 && !empty($latestNeedsProvisioningOrderId))
    <a href="{{ route('form-orders.show', [
            $latestNeedsProvisioningOrderId,
            'filter_no_participant' => 1,
            'course_id' => $courseId,
        ]) }}"
       class="badge bg-danger text-decoration-none"
       title="Otwórz ostatnie zamówienie bez uczestnika (#{{ $latestNeedsProvisioningOrderId }})">
        U {{ $ordersNeedingParticipants }}
    </a>
@elseif($ordersNeedingParticipants > 0)
    <a href="{{ route('form-orders.index', ['quick' => 'all', 'filter' => 'new', 'course_id' => $courseId]) }}"
       class="badge bg-danger text-decoration-none"
       title="Zamówienia, w których trzeba dodać uczestnika do szkolenia">
        U {{ $ordersNeedingParticipants }}
    </a>
@else
    <span class="badge bg-secondary" title="Brak zamówień z niedodanym uczestnikiem">U 0</span>
@endif
<br>
@if($ordersNeedingInvoice > 0 && !empty($latestNeedsInvoiceOrderId))
    <a href="{{ route('form-orders.show', [
            $latestNeedsInvoiceOrderId,
            'filter_no_invoice' => 1,
            'course_id' => $courseId,
        ]) }}"
       class="badge bg-warning text-dark text-decoration-none"
       title="Otwórz ostatnie zamówienie bez FV (#{{ $latestNeedsInvoiceOrderId }})">
        FV {{ $ordersNeedingInvoice }}
    </a>
@elseif($ordersNeedingInvoice > 0)
    <a href="{{ route('form-orders.index', ['quick' => 'all', 'filter' => 'needs_invoice', 'course_id' => $courseId]) }}"
       class="badge bg-warning text-dark text-decoration-none"
       title="Zamówienia bez wystawionej faktury i bez oznaczenia bez FV">
        FV {{ $ordersNeedingInvoice }}
    </a>
@else
    <span class="badge bg-secondary" title="Brak zamówień do wystawienia FV">FV 0</span>
@endif
