@php
    use App\Services\CourseFormOrderBillingService;

    $courseId = (int) ($courseId ?? 0);
    $bs = (string) ($closedBillingStatus ?? CourseFormOrderBillingService::STATUS_NOT_APPLICABLE);
@endphp
@if($bs !== CourseFormOrderBillingService::STATUS_NOT_APPLICABLE)
    @php
        $bsClass = CourseFormOrderBillingService::statusBadgeClass($bs);
        $bsLabel = CourseFormOrderBillingService::statusLabel($bs);
    @endphp
    <div class="text-center mb-1">
        @if($bs === CourseFormOrderBillingService::STATUS_NO_ORDERS)
            <a href="{{ route('form-orders.create', ['course_id' => $courseId]) }}"
               class="badge {{ $bsClass }} fw-semibold text-decoration-none"
               title="Dodaj zamówienie dla tego szkolenia">
                <i class="bi bi-receipt"></i> {{ $bsLabel }}
            </a>
        @elseif($bs === CourseFormOrderBillingService::STATUS_NO_INVOICE && !empty($closedBillingUninvoicedOrderId))
            <a href="{{ route('form-orders.show', $closedBillingUninvoicedOrderId) }}"
               class="badge {{ $bsClass }} fw-semibold text-decoration-none"
               title="Otwórz zamówienie bez wystawionej faktury (#{{ $closedBillingUninvoicedOrderId }})">
                <i class="bi bi-receipt"></i> {{ $bsLabel }}
            </a>
        @elseif($bs === CourseFormOrderBillingService::STATUS_COMPLETE && !empty($closedBillingFirstInvoiceNumber))
            <span class="badge {{ $bsClass }} fw-semibold"
                  title="Faktura: {{ $closedBillingFirstInvoiceNumber }}">
                <i class="bi bi-receipt"></i> {{ $bsLabel }} <span class="opacity-75">·</span> {{ $closedBillingFirstInvoiceNumber }}
            </span>
        @else
            <span class="badge {{ $bsClass }} fw-semibold"
                  title="Zamówienia: {{ $closedBillingOrdersTotal ?? 0 }}, z FV: {{ $closedBillingOrdersInvoiced ?? 0 }}">
                <i class="bi bi-receipt"></i> {{ $bsLabel }}
            </span>
        @endif
    </div>
@endif
