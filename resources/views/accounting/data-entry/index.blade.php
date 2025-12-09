<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            Wprowadź dane księgowe
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            {{-- Komunikaty --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Formularz wprowadzania danych --}}
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Dodaj nowy rekord przychodu</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('accounting.data-entry.store') }}" method="POST" id="revenueForm">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="year" class="form-label">Rok <span class="text-danger">*</span></label>
                                <select class="form-select @error('year') is-invalid @enderror" id="year" name="year" required>
                                    <option value="">-- Wybierz rok --</option>
                                    @for($y = date('Y'); $y >= 2000; $y--)
                                        <option value="{{ $y }}" {{ old('year', date('Y')) == $y ? 'selected' : '' }}>
                                            {{ $y }}
                                        </option>
                                    @endfor
                                </select>
                                @error('year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="month" class="form-label">Miesiąc <span class="text-danger">*</span></label>
                                <select class="form-select @error('month') is-invalid @enderror" id="month" name="month" required>
                                    <option value="">-- Wybierz miesiąc --</option>
                                    @php
                                        $months = [
                                            1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
                                            5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
                                            9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień'
                                        ];
                                    @endphp
                                    @foreach($months as $num => $name)
                                        <option value="{{ $num }}" {{ old('month', date('n')) == $num ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('month')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="amount" class="form-label">Kwota (PLN) <span class="text-danger">*</span></label>
                                <input type="number" 
                                       class="form-control @error('amount') is-invalid @enderror" 
                                       id="amount" 
                                       name="amount" 
                                       step="0.01" 
                                       min="0" 
                                       max="999999999.99"
                                       value="{{ old('amount') }}"
                                       placeholder="0.00"
                                       required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="source" class="form-label">Źródło</label>
                                <select class="form-select @error('source') is-invalid @enderror" id="source" name="source">
                                    <option value="manual" {{ old('source', 'manual') == 'manual' ? 'selected' : '' }}>Ręczne</option>
                                    <option value="ifirma" {{ old('source') == 'ifirma' ? 'selected' : '' }}>iFirma</option>
                                    <option value="other" {{ old('source') == 'other' ? 'selected' : '' }}>Inne</option>
                                </select>
                                @error('source')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notatki (opcjonalne)</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" 
                                          id="notes" 
                                          name="notes" 
                                          rows="2" 
                                          maxlength="1000"
                                          placeholder="Dodatkowe informacje o przychodzie...">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Zapisz rekord
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Wyczyść formularz
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Filtr i lista rekordów --}}
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Lista wprowadzonych rekordów</h5>
                        <form method="GET" action="{{ route('accounting.data-entry.index') }}" class="d-inline">
                            <div class="input-group" style="width: 200px;">
                                <select class="form-select form-select-sm" name="year" onchange="this.form.submit()">
                                    <option value="">Wszystkie lata</option>
                                    @foreach($availableYears as $year)
                                        <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    @if($revenueRecords->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Rok</th>
                                        <th>Miesiąc</th>
                                        <th>Kwota</th>
                                        <th>Źródło</th>
                                        <th>Notatki</th>
                                        <th>Wprowadził</th>
                                        <th>Data wprowadzenia</th>
                                        <th class="text-end">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($revenueRecords as $record)
                                        <tr>
                                            <td>{{ $record->year }}</td>
                                            <td>{{ $record->month_name }}</td>
                                            <td class="fw-bold text-success">{{ $record->formatted_amount }}</td>
                                            <td>
                                                @if($record->source == 'manual')
                                                    <span class="badge bg-primary">Ręczne</span>
                                                @elseif($record->source == 'ifirma')
                                                    <span class="badge bg-info">iFirma</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $record->source }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($record->notes)
                                                    <span class="text-muted" title="{{ $record->notes }}">
                                                        {{ Str::limit($record->notes, 50) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $record->user ? $record->user->name : '-' }}</td>
                                            <td>{{ $record->created_at->format('d.m.Y H:i') }}</td>
                                            <td class="text-end">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editModal{{ $record->id }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form action="{{ route('accounting.data-entry.destroy', $record->id) }}" 
                                                      method="POST" 
                                                      class="d-inline"
                                                      onsubmit="return confirm('Czy na pewno chcesz usunąć ten rekord?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                        {{-- Modal edycji --}}
                                        <div class="modal fade" id="editModal{{ $record->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('accounting.data-entry.update', $record->id) }}" method="POST">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edytuj rekord przychodu</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="edit_year{{ $record->id }}" class="form-label">Rok</label>
                                                                <select class="form-select" id="edit_year{{ $record->id }}" name="year" required>
                                                                    @for($y = date('Y'); $y >= 2000; $y--)
                                                                        <option value="{{ $y }}" {{ $record->year == $y ? 'selected' : '' }}>
                                                                            {{ $y }}
                                                                        </option>
                                                                    @endfor
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="edit_month{{ $record->id }}" class="form-label">Miesiąc</label>
                                                                <select class="form-select" id="edit_month{{ $record->id }}" name="month" required>
                                                                    @foreach($months as $num => $name)
                                                                        <option value="{{ $num }}" {{ $record->month == $num ? 'selected' : '' }}>
                                                                            {{ $name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="edit_amount{{ $record->id }}" class="form-label">Kwota (PLN)</label>
                                                                <input type="number" 
                                                                       class="form-control" 
                                                                       id="edit_amount{{ $record->id }}" 
                                                                       name="amount" 
                                                                       step="0.01" 
                                                                       min="0" 
                                                                       value="{{ $record->amount }}"
                                                                       required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="edit_source{{ $record->id }}" class="form-label">Źródło</label>
                                                                <select class="form-select" id="edit_source{{ $record->id }}" name="source">
                                                                    <option value="manual" {{ $record->source == 'manual' ? 'selected' : '' }}>Ręczne</option>
                                                                    <option value="ifirma" {{ $record->source == 'ifirma' ? 'selected' : '' }}>iFirma</option>
                                                                    <option value="other" {{ $record->source == 'other' ? 'selected' : '' }}>Inne</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="edit_notes{{ $record->id }}" class="form-label">Notatki</label>
                                                                <textarea class="form-control" 
                                                                          id="edit_notes{{ $record->id }}" 
                                                                          name="notes" 
                                                                          rows="3"
                                                                          maxlength="1000">{{ $record->notes }}</textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                                                            <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Paginacja --}}
                        <div class="mt-3">
                            {{ $revenueRecords->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-3">Brak rekordów przychodu. Dodaj pierwszy rekord używając formularza powyżej.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
