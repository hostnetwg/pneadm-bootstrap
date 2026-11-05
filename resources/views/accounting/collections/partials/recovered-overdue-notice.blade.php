@php($recoveredOverdueNotice = session(\App\Services\Accounting\RecoveredOverduePaymentReportService::SESSION_KEY))

@if(is_array($recoveredOverdueNotice) && filled($recoveredOverdueNotice['message'] ?? null))
    <div class="position-fixed top-0 end-0 p-3 pe-4" style="max-width: min(560px, calc(100vw - 1rem)); z-index: 1090;">
        <div class="alert alert-info border-success-subtle shadow-sm py-2 small mb-0" role="status">
            <i class="bi bi-cash-coin me-1"></i>
            {{ $recoveredOverdueNotice['message'] }}
            <a href="{{ route('accounting.collections.recovered-overdue') }}" class="alert-link ms-1">Zobacz raport</a>
        </div>
    </div>
@endif
