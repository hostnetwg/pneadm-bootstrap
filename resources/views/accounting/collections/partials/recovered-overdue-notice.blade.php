@php($recoveredOverdueNotice = session(\App\Services\Accounting\RecoveredOverduePaymentReportService::SESSION_KEY))

@if(is_array($recoveredOverdueNotice) && filled($recoveredOverdueNotice['message'] ?? null))
    <div class="alert alert-info border-success-subtle py-2 small" role="status">
        <i class="bi bi-cash-coin me-1"></i>
        {{ $recoveredOverdueNotice['message'] }}
        <a href="{{ route('accounting.collections.recovered-overdue') }}" class="alert-link ms-1">Zobacz raport</a>
    </div>
@endif
