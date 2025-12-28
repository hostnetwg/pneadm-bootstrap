<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-cloud-upload"></i> Import szkół z RSPO do Sendy
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid">
            {{-- Komunikaty sukcesu/błędu --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('import_results'))
                @php
                    $results = session('import_results');
                @endphp
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-check-circle me-2"></i>Wyniki importu
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Nazwa listy:</strong> {{ $results['list_name'] }}</p>
                                <p class="mb-1"><strong>ID listy:</strong> {{ $results['list_id'] }}</p>
                                <p class="mb-1"><strong>Przetworzono szkół:</strong> {{ $results['total_schools'] }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Dodani subskrybenci:</strong> <span class="text-success">{{ $results['subscribers_added'] }}</span></p>
                                <p class="mb-1"><strong>Nieudane subskrypcje:</strong> <span class="text-danger">{{ $results['subscribers_failed'] }}</span></p>
                            </div>
                        </div>
                        @if(!empty($results['errors']))
                            <div class="mt-3">
                                <strong>Błędy:</strong>
                                <ul class="mb-0">
                                    @foreach($results['errors'] as $error)
                                        <li class="text-danger">{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Formularz importu --}}
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel me-2"></i>Konfiguracja importu
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('rspo.import.import') }}">
                        @csrf

                        <div class="row g-3 mb-4">
                            {{-- Wybór listy Sendy --}}
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Wybierz listę Sendy</h6>
                            </div>

                            <div class="col-md-12">
                                <label for="list_id" class="form-label">
                                    <strong>Lista Sendy:</strong> <span class="text-danger">*</span>
                                </label>
                                <select name="list_id" 
                                        id="list_id" 
                                        class="form-select @error('list_id') is-invalid @enderror" 
                                        required>
                                    <option value="">-- Wybierz listę --</option>
                                    @if(isset($sendyLists) && is_array($sendyLists) && count($sendyLists) > 0)
                                        @foreach($sendyLists as $list)
                                            <option value="{{ $list['id'] ?? '' }}" {{ old('list_id') == ($list['id'] ?? '') ? 'selected' : '' }}>
                                                {{ $list['name'] ?? 'Brak nazwy' }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('list_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if(empty($sendyLists) || count($sendyLists) == 0)
                                    <div class="alert alert-warning mt-2">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Nie znaleziono list w Sendy dla Brand ID: {{ $brandId ?? 4 }}. 
                                        Utwórz listę ręcznie w Sendy, a następnie odśwież tę stronę.
                                    </div>
                                @else
                                    <small class="form-text text-muted">
                                        Wybierz listę, do której zostaną dodane szkoły. Jeśli lista nie istnieje, utwórz ją najpierw w Sendy.
                                    </small>
                                @endif
                            </div>
                        </div>

                        {{-- Lista typów placówek --}}
                        <div class="row">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Wybierz typy placówek do importu</h6>
                                
                                @if(empty($typesWithCounts))
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Nie udało się pobrać listy typów placówek. Spróbuj odświeżyć stronę.
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 50px;">
                                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                                        <label for="selectAll" class="form-check-label ms-2">Wszystkie</label>
                                                    </th>
                                                    <th>Typ placówki</th>
                                                    <th class="text-end">Liczba placówek</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($typesWithCounts as $type)
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" 
                                                                   name="type_ids[]" 
                                                                   value="{{ $type['id'] }}" 
                                                                   id="type_{{ $type['id'] }}"
                                                                   class="form-check-input type-checkbox">
                                                        </td>
                                                        <td>
                                                            <label for="type_{{ $type['id'] }}" class="form-check-label cursor-pointer">
                                                                {{ $type['nazwa'] }}
                                                            </label>
                                                        </td>
                                                        <td class="text-end">
                                                            @if($type['count'] !== null)
                                                                <span class="badge bg-primary">{{ number_format($type['count'], 0, ',', ' ') }}</span>
                                                            @else
                                                                <span class="badge bg-secondary">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Przycisk submit --}}
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-cloud-upload me-2"></i>Utwórz listę i zaimportuj szkoły
                                    </button>
                                </div>
                                <small class="form-text text-muted d-block mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Wszystkie szkoły z zaznaczonych typów placówek zostaną dodane do wybranej listy Sendy.
                                    Każda szkoła będzie dodana z danymi: nazwa, email, RSPO.
                                </small>
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-clock me-2"></i>
                                    <strong>Uwaga:</strong> Import może zająć kilka minut w zależności od liczby szkół. 
                                    Nie zamykaj strony podczas importu. Proces może trwać do 10 minut dla dużych importów.
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const typeCheckboxes = document.querySelectorAll('.type-checkbox');

            // Zaznacz/odznacz wszystkie
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    typeCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            // Sprawdź czy wszystkie są zaznaczone przy zmianie pojedynczego checkboxa
            typeCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (selectAll) {
                        const allChecked = Array.from(typeCheckboxes).every(cb => cb.checked);
                        selectAll.checked = allChecked;
                    }
                });
            });

            // Potwierdzenie przed wysłaniem formularza
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const checkedBoxes = document.querySelectorAll('.type-checkbox:checked');
                    if (checkedBoxes.length === 0) {
                        e.preventDefault();
                        alert('Proszę zaznaczyć przynajmniej jeden typ placówki.');
                        return false;
                    }

                    if (!confirm('Czy na pewno chcesz utworzyć listę i zaimportować szkoły? Proces może zająć dużo czasu.')) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    </script>
    <style>
        .cursor-pointer {
            cursor: pointer;
        }
    </style>
    @endpush
</x-app-layout>




