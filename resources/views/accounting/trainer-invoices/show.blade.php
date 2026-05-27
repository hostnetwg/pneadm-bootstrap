@php
    $totalGross = $invoice->items->sum(fn ($i) => (float) $i->amount_gross);
    $listFilters = $listFilters ?? [];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Faktura trenera: {{ $invoice->invoice_number }}</h2>
            <a href="{{ route('accounting.trainer-invoices.index', $listFilters) }}" class="btn btn-sm btn-outline-secondary">← Lista faktur{{ !empty($listFilters) ? ' (z filtrami)' : '' }}</a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header fw-semibold">Dane faktury</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accounting.trainer-invoices.update', array_merge(['trainerInvoice' => $invoice], $listFilters)) }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Numer faktury</label>
                                        <input type="text" name="invoice_number" class="form-control @error('invoice_number') is-invalid @enderror" value="{{ old('invoice_number', $invoice->invoice_number) }}" required maxlength="64">
                                        @error('invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Numer KSeF</label>
                                        <input type="text" name="ksef_number" class="form-control" value="{{ old('ksef_number', $invoice->ksef_number) }}" maxlength="128">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Data faktury</label>
                                        <input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', $invoice->invoice_date?->format('Y-m-d')) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Status płatności</label>
                                        <select name="payment_status" class="form-select">
                                            <option value="unpaid" @selected(old('payment_status', $invoice->payment_status) === 'unpaid')>Nieopłacona</option>
                                            <option value="paid" @selected(old('payment_status', $invoice->payment_status) === 'paid')>Opłacona</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Data zapłaty</label>
                                        <input type="date" name="paid_at" class="form-control" value="{{ old('paid_at', $invoice->paid_at?->format('Y-m-d')) }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notatki</label>
                                    <textarea name="notes" class="form-control" rows="3" maxlength="2000">{{ old('notes', $invoice->notes) }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Trener</div>
                        <div class="card-body">
                            <p class="mb-1"><strong>{{ $invoice->instructor?->getFullTitleNameAttribute() }}</strong></p>
                            @if($invoice->instructor?->email)
                                <p class="mb-0 small text-muted">{{ $invoice->instructor->email }}</p>
                            @endif
                            <hr>
                            <p class="mb-1">Pozycji na fakturze: <strong>{{ $invoice->items->count() }}</strong></p>
                            <p class="mb-0">Suma kwot: <strong>{{ number_format($totalGross, 2, ',', ' ') }} zł</strong></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Pozycje — szkolenia na fakturze</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID szkolenia</th>
                                    <th>Tytuł / data</th>
                                    <th class="text-end">Kwota brutto</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoice->items as $item)
                                    <tr>
                                        <td>{{ $item->course_id }}</td>
                                        <td>
                                            @if($item->course)
                                                <a href="{{ route('courses.show', $item->course_id) }}" class="text-decoration-none" target="_blank">
                                                    {!! \Illuminate\Support\Str::limit(strip_tags($item->course->title), 60) !!}
                                                </a>
                                                <div class="small text-muted">
                                                    {{ $item->course->start_date?->format('d.m.Y H:i') ?? '—' }}
                                                </div>
                                            @else
                                                <span class="text-muted">Szkolenie usunięte</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ number_format((float) $item->amount_gross, 2, ',', ' ') }} zł</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('accounting.trainer-invoices.items.destroy', array_merge(['trainerInvoice' => $invoice, 'item' => $item], $listFilters)) }}" class="d-inline" onsubmit="return confirm('Usunąć tę pozycję ze szkolenia? Faktura pozostanie.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Usuń pozycję</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Brak pozycji — faktura bez przypisanych szkoleń.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($invoice->items->isNotEmpty())
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2" class="text-end">Razem:</th>
                                        <th class="text-end">{{ number_format($totalGross, 2, ',', ' ') }} zł</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-danger">
                <div class="card-header bg-danger text-white fw-semibold">Strefa niebezpieczna</div>
                <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                    @if(!$invoice->isPaid())
                        <form method="POST" action="{{ route('accounting.trainer-invoices.mark-paid', array_merge(['trainerInvoice' => $invoice], $listFilters)) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="paid_at" value="{{ now()->format('Y-m-d') }}">
                            <button type="submit" class="btn btn-success btn-sm">Oznacz jako opłaconą (dziś)</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('accounting.trainer-invoices.mark-unpaid', array_merge(['trainerInvoice' => $invoice], $listFilters)) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm">Oznacz jako nieopłaconą</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('accounting.trainer-invoices.destroy', array_merge(['trainerInvoice' => $invoice], $listFilters)) }}" class="d-inline ms-auto" onsubmit="return confirm('Na pewno usunąć całą fakturę {{ $invoice->invoice_number }} wraz ze wszystkimi pozycjami? Tej operacji nie można cofnąć.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Usuń całą fakturę</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
