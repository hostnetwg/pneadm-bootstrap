@php
    $period = $filters['period'] ?? \App\Support\TrainerInvoicePeriodFilter::PERIOD_CURRENT_MONTH;
    $isCustomPeriod = $period === \App\Support\TrainerInvoicePeriodFilter::PERIOD_CUSTOM;
    $defaultParams = \App\Support\TrainerInvoicePeriodFilter::defaultQueryParams();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">Faktury trenerów</h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('accounting.trainer-invoices.index') }}" id="trainerInvoicesFilterForm" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-0">Okres</label>
                            <select name="period" id="filterPeriod" class="form-select form-select-sm">
                                @foreach($periodOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-0">Trener</label>
                            <select name="instructor_id" class="form-select form-select-sm">
                                <option value="">— wszyscy —</option>
                                @foreach($instructors as $instructor)
                                    <option value="{{ $instructor->id }}" @selected(($filters['instructor_id'] ?? '') == $instructor->id)>
                                        {{ $instructor->getFullTitleNameAttribute() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Status</label>
                            <select name="payment_status" class="form-select form-select-sm">
                                <option value="">— wszystkie —</option>
                                <option value="unpaid" @selected(($filters['payment_status'] ?? '') === 'unpaid')>Nieopłacone</option>
                                <option value="paid" @selected(($filters['payment_status'] ?? '') === 'paid')>Opłacone</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Data od</label>
                            <input type="date" name="date_from" id="filterDateFrom" class="form-control form-control-sm"
                                   value="{{ $filters['date_from'] ?? '' }}" @readonly(!$isCustomPeriod)>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Data do</label>
                            <input type="date" name="date_to" id="filterDateTo" class="form-control form-control-sm"
                                   value="{{ $filters['date_to'] ?? '' }}" @readonly(!$isCustomPeriod)>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small mb-0">Szukaj (nr, KSeF, notatki)</label>
                            <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="np. FV/5/2026">
                        </div>
                        <div class="col-md-12 d-flex gap-2 mt-1">
                            <button type="submit" class="btn btn-primary btn-sm">Filtruj</button>
                            <a href="{{ route('accounting.trainer-invoices.index', $defaultParams) }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-light border mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="text-muted">Okres:</span> <strong>{{ $periodLabel }}</strong>
                    @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                        <span class="text-muted ms-2">({{ !empty($filters['date_from']) ? \Carbon\Carbon::parse($filters['date_from'])->format('d.m.Y') : '…' }}
                        – {{ !empty($filters['date_to']) ? \Carbon\Carbon::parse($filters['date_to'])->format('d.m.Y') : '…' }})</span>
                    @endif
                </div>
                <div class="text-end">
                    <div><span class="text-muted">Faktur:</span> <strong>{{ $filteredInvoicesCount }}</strong></div>
                    <div class="fs-5 fw-semibold text-primary">
                        Suma pozycji: {{ number_format($filteredTotalGross, 2, ',', ' ') }} zł
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nr faktury</th>
                                    <th>Trener</th>
                                    <th>Data FV</th>
                                    <th>KSeF</th>
                                    <th class="text-center">Szkoleń</th>
                                    <th class="text-end">Suma pozycji</th>
                                    <th>Status</th>
                                    <th>Data zapłaty</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoices as $invoice)
                                    <tr>
                                        <td class="fw-semibold">{{ $invoice->invoice_number }}</td>
                                        <td>{{ $invoice->instructor?->getFullTitleNameAttribute() ?? '—' }}</td>
                                        <td>{{ $invoice->invoice_date?->format('d.m.Y') ?? '—' }}</td>
                                        <td class="small text-muted">{{ $invoice->ksef_number ?: '—' }}</td>
                                        <td class="text-center">{{ $invoice->items_count }}</td>
                                        <td class="text-end">{{ number_format((float) ($invoice->items_total_gross ?? 0), 2, ',', ' ') }} zł</td>
                                        <td>
                                            @if($invoice->isPaid())
                                                <span class="badge bg-success">Opłacona</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Nieopłacona</span>
                                            @endif
                                        </td>
                                        <td>{{ $invoice->paid_at?->format('d.m.Y') ?? '—' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('accounting.trainer-invoices.show', array_merge(['trainerInvoice' => $invoice], $filters)) }}" class="btn btn-sm btn-outline-primary">Szczegóły</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">Brak faktur trenerów spełniających kryteria.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($invoices->hasPages())
                    <div class="card-footer">{{ $invoices->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('trainerInvoicesFilterForm');
        const periodSelect = document.getElementById('filterPeriod');
        const dateFrom = document.getElementById('filterDateFrom');
        const dateTo = document.getElementById('filterDateTo');

        function syncDateFieldsReadonly() {
            const isCustom = periodSelect.value === 'custom';
            dateFrom.readOnly = !isCustom;
            dateTo.readOnly = !isCustom;
            dateFrom.classList.toggle('bg-light', !isCustom);
            dateTo.classList.toggle('bg-light', !isCustom);
        }

        periodSelect.addEventListener('change', function() {
            if (periodSelect.value !== 'custom') {
                form.submit();
                return;
            }
            syncDateFieldsReadonly();
        });

        [dateFrom, dateTo].forEach(function(el) {
            el.addEventListener('change', function() {
                if (periodSelect.value !== 'custom') {
                    periodSelect.value = 'custom';
                    syncDateFieldsReadonly();
                }
            });
        });

        syncDateFieldsReadonly();
    });
    </script>
    @endpush
</x-app-layout>
