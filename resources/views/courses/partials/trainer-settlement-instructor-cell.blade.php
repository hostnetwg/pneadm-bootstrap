@php
    use App\Support\TrainerSettlement;
    $canManageTrainerSettlement = auth()->user()?->isSuperAdmin() ?? false;
    $inScope = TrainerSettlement::isCourseInScope($course);
    $item = $canManageTrainerSettlement ? $course->trainerSettlementItem : null;
    $invoice = $item?->trainerInvoice;
@endphp
<div class="trainer-settlement-cell">
    <div>{{ $course->instructor ? $course->instructor->getFullTitleNameAttribute() : 'Brak instruktora' }}</div>
    @if($canManageTrainerSettlement && $course->instructor)
        @if(!$inScope)
            <div class="text-muted small mt-1" title="Rozliczenia od {{ TrainerSettlement::cutoffDate()->format('d.m.Y') }}">
                <i class="bi bi-dash-circle"></i> Poza zakresem
            </div>
        @else
            <div class="small mt-1">
                @if($item && $invoice)
                    <div class="mb-1">
                        @if($invoice->isPaid())
                            <span class="badge bg-success" title="Opłacono{{ $invoice->paid_at ? ' ' . $invoice->paid_at->format('d.m.Y') : '' }}">Opłacona</span>
                        @else
                            <span class="badge bg-warning text-dark">Nieopłacona</span>
                        @endif
                    </div>
                    <div class="text-nowrap" title="Numer faktury trenera">
                        <i class="bi bi-receipt"></i> {{ $invoice->invoice_number }}
                    </div>
                    @if($invoice->ksef_number)
                        <div class="text-muted text-truncate" style="max-width: 8rem;" title="KSeF: {{ $invoice->ksef_number }}">
                            KSeF: {{ $invoice->ksef_number }}
                        </div>
                    @endif
                    <div class="fw-semibold" title="Kwota wypłaty za to szkolenie">
                        {{ number_format((float) $item->amount_gross, 2, ',', ' ') }} zł
                    </div>
                    <button type="button"
                            class="btn btn-link btn-sm p-0 mt-1 d-block trainer-settlement-open"
                            data-course-id="{{ $course->id }}"
                            data-instructor-id="{{ $course->instructor_id }}"
                            data-instructor-name="{{ $course->instructor->getFullTitleNameAttribute() }}"
                            title="Rozliczenie trenera">
                        <i class="bi bi-pencil-square"></i> Edytuj
                    </button>
                @else
                    <div class="text-muted">
                        <i class="bi bi-exclamation-circle"></i> Brak rozliczenia
                    </div>
                    <button type="button"
                            class="btn btn-link btn-sm p-0 mt-1 d-block trainer-settlement-open"
                            data-course-id="{{ $course->id }}"
                            data-instructor-id="{{ $course->instructor_id }}"
                            data-instructor-name="{{ $course->instructor->getFullTitleNameAttribute() }}"
                            title="Rozliczenie trenera">
                        <i class="bi bi-pencil-square"></i> Dodaj
                    </button>
                @endif
            </div>
        @endif
    @endif
</div>
