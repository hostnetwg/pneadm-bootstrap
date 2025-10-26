<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Dodaj nowy produkt formularza
            </h2>
            <div>
                <a href="{{ route('certgen.zamowienia_prod.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Powrót do listy
                </a>
            </div>
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">

            {{-- Komunikaty błędów --}}
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Błędy walidacji:</h5>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form action="{{ route('certgen.zamowienia_prod.store') }}" method="POST" id="produktForm">
                @csrf

                {{-- Dane podstawowe produktu --}}
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-box"></i> Dane podstawowe produktu</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="nazwa" class="form-label">Nazwa produktu <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control @error('nazwa') is-invalid @enderror" 
                                       id="nazwa" 
                                       name="nazwa" 
                                       value="{{ old('nazwa') }}" 
                                       required>
                                @error('nazwa')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="idProdPubligo" class="form-label">ID Publigo</label>
                                <input type="number" 
                                       class="form-control @error('idProdPubligo') is-invalid @enderror" 
                                       id="idProdPubligo" 
                                       name="idProdPubligo" 
                                       value="{{ old('idProdPubligo') }}">
                                @error('idProdPubligo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">ID produktu w systemie Publigo</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="price_id_ProdPubligo" class="form-label">ID Ceny Publigo</label>
                                <input type="number" 
                                       class="form-control @error('price_id_ProdPubligo') is-invalid @enderror" 
                                       id="price_id_ProdPubligo" 
                                       name="price_id_ProdPubligo" 
                                       value="{{ old('price_id_ProdPubligo') }}">
                                @error('price_id_ProdPubligo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">ID ceny w systemie Publigo</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select @error('status') is-invalid @enderror" 
                                        id="status" 
                                        name="status" 
                                        required>
                                    <option value="1" {{ old('status', '1') == '1' ? 'selected' : '' }}>Aktywny</option>
                                    <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Nieaktywny</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="promocja" class="form-label">Promocja / Informacje dodatkowe</label>
                                <input type="text" 
                                       class="form-control @error('promocja') is-invalid @enderror" 
                                       id="promocja" 
                                       name="promocja" 
                                       value="{{ old('promocja') }}"
                                       placeholder="np. ilość miejsc ograniczona">
                                @error('promocja')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Dodatkowe informacje o promocji lub ograniczeniach</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Warianty cenowe --}}
                <div class="card mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-tag"></i> Warianty cenowe</h5>
                        <button type="button" class="btn btn-light btn-sm" id="addWariant">
                            <i class="bi bi-plus-circle"></i> Dodaj wariant
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="wariantyContainer">
                            {{-- Warianty będą dodawane tutaj przez JavaScript --}}
                        </div>
                        <div class="text-muted small mt-2">
                            <i class="bi bi-info-circle"></i> Możesz dodać wiele wariantów cenowych dla tego produktu
                        </div>
                    </div>
                </div>

                {{-- Przyciski akcji --}}
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('certgen.zamowienia_prod.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Zapisz produkt
                    </button>
                </div>
            </form>

        </div>
    </div>

    @push('scripts')
    <script>
        let wariantCounter = 0;

        // Szablon wariantu cenowego
        function createWariantHTML(index) {
            return `
                <div class="wariant-item border rounded p-3 mb-3" data-index="${index}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-tag"></i> Wariant #<span class="wariant-number">${index + 1}</span></h6>
                        <button type="button" class="btn btn-danger btn-sm remove-wariant">
                            <i class="bi bi-trash"></i> Usuń
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Lp <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control" 
                                   name="warianty[${index}][lp]" 
                                   value="${index + 1}" 
                                   required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Opis wariantu <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   name="warianty[${index}][opis]" 
                                   placeholder="np. dostęp dla jednej osoby" 
                                   required>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Cena <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control" 
                                   name="warianty[${index}][cena]" 
                                   step="0.01" 
                                   min="0" 
                                   placeholder="0.00" 
                                   required>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="warianty[${index}][status]" required>
                                <option value="1" selected>Aktywny</option>
                                <option value="0">Nieaktywny</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cena promocyjna</label>
                            <input type="number" 
                                   class="form-control" 
                                   name="warianty[${index}][cena_prom]" 
                                   step="0.01" 
                                   min="0" 
                                   placeholder="0.00">
                            <small class="text-muted">Opcjonalnie</small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Początek promocji</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   name="warianty[${index}][data_p_start]">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Koniec promocji</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   name="warianty[${index}][data_p_end]">
                        </div>
                    </div>
                </div>
            `;
        }

        // Dodaj pierwszy wariant przy załadowaniu strony
        document.addEventListener('DOMContentLoaded', function() {
            addWariant();
        });

        // Funkcja dodawania wariantu
        function addWariant() {
            const container = document.getElementById('wariantyContainer');
            const newWariant = document.createElement('div');
            newWariant.innerHTML = createWariantHTML(wariantCounter);
            container.appendChild(newWariant.firstElementChild);
            wariantCounter++;
            updateWariantNumbers();
        }

        // Obsługa przycisku "Dodaj wariant"
        document.getElementById('addWariant').addEventListener('click', addWariant);

        // Obsługa usuwania wariantów (delegacja zdarzeń)
        document.getElementById('wariantyContainer').addEventListener('click', function(e) {
            if (e.target.closest('.remove-wariant')) {
                const wariantItem = e.target.closest('.wariant-item');
                wariantItem.remove();
                updateWariantNumbers();
            }
        });

        // Aktualizacja numeracji wariantów
        function updateWariantNumbers() {
            const warianty = document.querySelectorAll('.wariant-item');
            warianty.forEach((wariant, index) => {
                const numberSpan = wariant.querySelector('.wariant-number');
                if (numberSpan) {
                    numberSpan.textContent = index + 1;
                }
                // Aktualizuj wartość Lp
                const lpInput = wariant.querySelector('input[name*="[lp]"]');
                if (lpInput) {
                    lpInput.value = index + 1;
                }
            });
        }

        // Walidacja przed wysłaniem formularza
        document.getElementById('produktForm').addEventListener('submit', function(e) {
            const warianty = document.querySelectorAll('.wariant-item');
            if (warianty.length === 0) {
                e.preventDefault();
                alert('Dodaj przynajmniej jeden wariant cenowy!');
                return false;
            }
        });
    </script>
    @endpush
</x-app-layout>

