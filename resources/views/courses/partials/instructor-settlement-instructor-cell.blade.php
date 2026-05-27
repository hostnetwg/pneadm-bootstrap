@php
    use App\Support\InstructorSettlement;
    $canManageInstructorSettlement = auth()->user()?->isSuperAdmin() ?? false;
    $inScope = InstructorSettlement::isCourseInScope($course);
    $item = $canManageInstructorSettlement ? $course->instructorSettlementItem : null;
    $invoice = $item?->instructorInvoice;
@endphp
<div class="instructor-settlement-cell">
    <div>{{ $course->instructor ? $course->instructor->getFullTitleNameAttribute() : 'Brak instruktora' }}</div>
    @if($canManageInstructorSettlement && $course->instructor)
        @if(!$inScope)
            <div class="text-muted small mt-1" title="Rozliczenia od {{ InstructorSettlement::cutoffDate()->format('d.m.Y') }}">
                <i class="bi bi-dash-circle"></i> Poza zakresem
            </div>
        @else
            <div class="small mt-1">
                @if($item && $invoice)
                    <div class="mb-1 d-flex flex-wrap gap-1 align-items-center">
                        @if($invoice->isMandate())
                            <span class="badge bg-info text-dark" title="Umowa zlecenie">UZ</span>
                        @else
                            <span class="badge bg-secondary" title="Faktura">FV</span>
                        @endif
                        @if($invoice->isPaid())
                            <span class="badge bg-success" title="Opłacono{{ $invoice->paid_at ? ' ' . $invoice->paid_at->format('d.m.Y') : '' }}">Opłacona</span>
                        @else
                            <span class="badge bg-warning text-dark">Nieopłacona</span>
                        @endif
                    </div>
                    <div class="text-nowrap" title="Numer dokumentu rozliczeniowego">
                        @if($invoice->isMandate())<i class="bi bi-file-earmark-text"></i>@else<i class="bi bi-receipt"></i>@endif {{ $invoice->invoice_number }}
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
                            class="btn btn-link btn-sm p-0 mt-1 d-block instructor-settlement-open"
                            data-course-id="{{ $course->id }}"
                            data-instructor-id="{{ $course->instructor_id }}"
                            data-instructor-name="{{ $course->instructor->getFullTitleNameAttribute() }}"
                            title="Rozliczenie instruktora">
                        <i class="bi bi-pencil-square"></i> Edytuj
                    </button>
                @else
                    <div class="text-muted">
                        <i class="bi bi-exclamation-circle"></i> Brak rozliczenia
                    </div>
                    <button type="button"
                            class="btn btn-link btn-sm p-0 mt-1 d-block instructor-settlement-open"
                            data-course-id="{{ $course->id }}"
                            data-instructor-id="{{ $course->instructor_id }}"
                            data-instructor-name="{{ $course->instructor->getFullTitleNameAttribute() }}"
                            title="Rozliczenie instruktora">
                        <i class="bi bi-pencil-square"></i> Dodaj
                    </button>
                @endif
            </div>
        @endif
    @endif
</div>
