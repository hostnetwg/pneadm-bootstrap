<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-search"></i> Wyszukiwarka RSPO
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid">
            {{-- Formularz wyszukiwania --}}
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel"></i> Zaawansowane wyszukiwanie
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('rspo.search') }}" id="searchForm" class="row g-3">
                        {{-- Typ podmiotu --}}
                        <div class="col-md-6">
                            <label for="typ_podmiotu_id" class="form-label">
                                <strong>Typ podmiotu:</strong>
                            </label>
                            <select name="typ_podmiotu_id" id="typ_podmiotu_id" class="form-select">
                                <option value="" {{ !$selectedTypeId ? 'selected' : '' }}>-- Wszystkie typy --</option>
                                @if(isset($types) && is_array($types) && count($types) > 0)
                                    @foreach($types as $type)
                                        @php
                                            $typeId = $type['id'] ?? null;
                                            $typeName = $type['nazwa'] ?? 'Brak nazwy';
                                            $typeCount = $type['count'] ?? null;
                                        @endphp
                                        @if($typeId)
                                            <option value="{{ $typeId }}" {{ $selectedTypeId == $typeId ? 'selected' : '' }}>
                                                {{ $typeName }}@if(isset($type['count']) && $type['count'] !== null) ({{ number_format($type['count'], 0, ',', ' ') }})@endif
                                            </option>
                                        @endif
                                    @endforeach
                                @else
                                    <option value="" disabled>Brak dostępnych typów (błąd połączenia z API)</option>
                                @endif
                            </select>
                        </div>

                        {{-- Województwo --}}
                        <div class="col-md-6">
                            <label for="wojewodztwo_nazwa" class="form-label">
                                <strong>Województwo:</strong>
                            </label>
                            <select name="wojewodztwo_nazwa" id="wojewodztwo_nazwa" class="form-select">
                                <option value="">-- Wszystkie województwa --</option>
                                @if(isset($wojewodztwa) && is_array($wojewodztwa))
                                    @foreach($wojewodztwa as $woj)
                                        <option value="{{ $woj['nazwa'] ?? '' }}" 
                                                data-kod="{{ $woj['kod'] ?? '' }}"
                                                {{ ($selectedWojewodztwo ?? '') == ($woj['nazwa'] ?? '') ? 'selected' : '' }}>
                                            {{ $woj['nazwa'] ?? 'Brak nazwy' }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        {{-- Powiat --}}
                        <div class="col-md-6">
                            <label for="powiat_nazwa" class="form-label">
                                <strong>Powiat:</strong>
                            </label>
                            <select name="powiat_nazwa" id="powiat_nazwa" class="form-select" {{ empty($powiaty) ? 'disabled' : '' }}>
                                <option value="">-- Wybierz powiat --</option>
                                @if(isset($powiaty) && is_array($powiaty) && count($powiaty) > 0)
                                    @foreach($powiaty as $pow)
                                        <option value="{{ $pow['nazwa'] ?? '' }}" 
                                                data-kod="{{ $pow['kod'] ?? '' }}"
                                                data-woj-kod="{{ $pow['wojewodztwo_kod'] ?? '' }}"
                                                {{ ($selectedPowiat ?? '') == ($pow['nazwa'] ?? '') ? 'selected' : '' }}>
                                            {{ $pow['nazwa'] ?? 'Brak nazwy' }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <small class="form-text text-muted">Wybierz najpierw województwo</small>
                        </div>

                        {{-- Miejscowość --}}
                        <div class="col-md-6">
                            <label for="miejscowosc_nazwa" class="form-label">
                                <strong>Miejscowość:</strong>
                            </label>
                            <select name="miejscowosc_nazwa" id="miejscowosc_nazwa" class="form-select" {{ empty($miejscowosci) ? 'disabled' : '' }}>
                                <option value="">-- Wybierz miejscowość --</option>
                                @if(isset($miejscowosci) && is_array($miejscowosci) && count($miejscowosci) > 0)
                                    @foreach($miejscowosci as $miejsc)
                                        <option value="{{ $miejsc['nazwa'] ?? '' }}" 
                                                {{ ($selectedMiejscowosc ?? '') == ($miejsc['nazwa'] ?? '') ? 'selected' : '' }}>
                                            {{ $miejsc['nazwa'] ?? 'Brak nazwy' }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <small class="form-text text-muted">Wybierz najpierw powiat</small>
                        </div>

                        {{-- Przycisk wyszukiwania --}}
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i>Szukaj
                            </button>
                            <a href="{{ route('rspo.search') }}" class="btn btn-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Wyczyść
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Komunikat o błędzie --}}
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Wyniki wyszukiwania --}}
            @if(isset($results))
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>
                            Wyniki wyszukiwania
                            @if(isset($results) && count($results) > 0 && isset($pagination))
                                <span class="badge bg-light text-dark ms-2">
                                    @if(isset($pagination['total_items']) && $pagination['total_items'] > 0)
                                        Znaleziono: <strong>{{ number_format($pagination['total_items'], 0, ',', ' ') }}</strong> {{ $pagination['total_items'] == 1 ? 'placówka' : ($pagination['total_items'] < 5 ? 'placówki' : 'placówek') }}
                                        @if($pagination['total_pages'] && $pagination['total_pages'] > 1)
                                            (strona {{ $page }} z {{ $pagination['total_pages'] }})
                                        @endif
                                    @else
                                        {{ count($results) }} {{ count($results) == 1 ? 'wynik' : (count($results) < 5 ? 'wyniki' : 'wyników') }}
                                        @if($pagination['has_next'])
                                            (strona {{ $page }})
                                        @endif
                                    @endif
                                </span>
                            @endif
                        </h5>
                    </div>
                    <div class="card-body">
                        @if(count($results) > 0)
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>RSPO</th>
                                            <th>Nazwa</th>
                                            <th>Adres</th>
                                            <th>Województwo</th>
                                            <th>Dyrektor</th>
                                            <th>Liczba uczniów</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($results as $placowka)
                                            @php
                                                $numerRspo = $placowka['numerRspo'] ?? '-';
                                                $nazwa = $placowka['nazwa'] ?? $placowka['nazwaSkrocona'] ?? 'Brak nazwy';
                                                $miejscowosc = $placowka['miejscowosc'] ?? '-';
                                                $ulica = ($placowka['ulica'] ?? '') . ' ' . ($placowka['numerBudynku'] ?? '');
                                                $ulica = trim($ulica) ?: '-';
                                                $kodPocztowy = $placowka['kodPocztowy'] ?? '-';
                                                $wojewodztwo = $placowka['wojewodztwo'] ?? '-';
                                                $liczbaUczniow = $placowka['liczbaUczniow'] ?? null;
                                                $stronaInternetowa = $placowka['stronaInternetowa'] ?? null;
                                                $email = $placowka['email'] ?? null;
                                                $dyrektorImie = $placowka['dyrektorImie'] ?? null;
                                                $dyrektorNazwisko = $placowka['dyrektorNazwisko'] ?? null;
                                                
                                                // Upewnij się, że link ma protokół http:// lub https://
                                                if ($stronaInternetowa && !preg_match('/^https?:\/\//i', $stronaInternetowa)) {
                                                    $stronaInternetowa = 'https://' . $stronaInternetowa;
                                                }
                                                
                                                // Przygotuj dane dyrektora
                                                $dyrektor = null;
                                                if ($dyrektorImie || $dyrektorNazwisko) {
                                                    $dyrektor = trim(($dyrektorImie ?? '') . ' ' . ($dyrektorNazwisko ?? ''));
                                                }
                                            @endphp
                                            <tr>
                                                <td><strong>{{ $numerRspo }}</strong></td>
                                                <td>
                                                    <div class="fw-semibold">{{ $nazwa }}</div>
                                                    @if($stronaInternetowa)
                                                        <div class="mt-1">
                                                            <a href="{{ $stronaInternetowa }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-primary">
                                                                <i class="bi bi-globe me-1"></i>
                                                                <small>{{ $placowka['stronaInternetowa'] ?? $stronaInternetowa }}</small>
                                                            </a>
                                                        </div>
                                                    @endif
                                                    @if($email)
                                                        <div class="mt-1">
                                                            <a href="mailto:{{ $email }}" class="text-decoration-none text-primary">
                                                                <i class="bi bi-envelope me-1"></i>
                                                                <small>{{ $email }}</small>
                                                            </a>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $adresParts = [];
                                                        if ($ulica && $ulica !== '-') {
                                                            $adresParts[] = $ulica;
                                                        }
                                                        if ($kodPocztowy && $kodPocztowy !== '-') {
                                                            $adresParts[] = $kodPocztowy;
                                                        }
                                                        if ($miejscowosc && $miejscowosc !== '-') {
                                                            $adresParts[] = $miejscowosc;
                                                        }
                                                    @endphp
                                                    @if(count($adresParts) > 0)
                                                        <div>
                                                            @foreach($adresParts as $part)
                                                                {{ $part }}@if(!$loop->last)<br>@endif
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ $wojewodztwo }}</td>
                                                <td>
                                                    @if($dyrektor)
                                                        {{ $dyrektor }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($liczbaUczniow !== null)
                                                        {{ number_format($liczbaUczniow, 0, ',', ' ') }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Paginacja --}}
                            @if($pagination && ($pagination['has_next'] || $pagination['has_previous']))
                                <nav aria-label="Paginacja wyników">
                                    <ul class="pagination justify-content-center">
                                        @php
                                            $paginationParams = ['page' => $page - 1];
                                            if ($selectedTypeId) {
                                                $paginationParams['typ_podmiotu_id'] = $selectedTypeId;
                                            }
                                        @endphp
                                        @if($pagination['has_previous'] && $page > 1)
                                            <li class="page-item">
                                                <a class="page-link" href="{{ route('rspo.search', $paginationParams) }}">
                                                    <i class="bi bi-chevron-left"></i> Poprzednia
                                                </a>
                                            </li>
                                        @endif
                                        
                                        <li class="page-item active">
                                            <span class="page-link">Strona {{ $page }}</span>
                                        </li>
                                        
                                        @php
                                            $paginationParams = ['page' => $page + 1];
                                            if ($selectedTypeId) {
                                                $paginationParams['typ_podmiotu_id'] = $selectedTypeId;
                                            }
                                        @endphp
                                        @if($pagination['has_next'])
                                            <li class="page-item">
                                                <a class="page-link" href="{{ route('rspo.search', $paginationParams) }}">
                                                    Następna <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        @endif
                                    </ul>
                                </nav>
                            @endif
                        @else
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Nie znaleziono placówek dla wybranego typu podmiotu.
                            </div>
                        @endif
                    </div>
                </div>
            @elseif($selectedTypeId)
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Nie udało się pobrać wyników. Spróbuj ponownie później.
                </div>
            @elseif(!$selectedTypeId)
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-search display-4 text-muted mb-3"></i>
                        <h5 class="text-muted">Wybierz typ szkoły/placówki, aby rozpocząć wyszukiwanie</h5>
                        <p class="text-muted small mt-2">
                            Wyszukiwarka korzysta z publicznego API Rejestru Szkół i Placówek Oświatowych (RSPO)
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const wojewodztwoSelect = document.getElementById('wojewodztwo_nazwa');
            const powiatSelect = document.getElementById('powiat_nazwa');
            const miejscowoscSelect = document.getElementById('miejscowosc_nazwa');

            // Obsługa zmiany województwa
            if (wojewodztwoSelect) {
                wojewodztwoSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const wojewodztwoKod = selectedOption.getAttribute('data-kod');
                    const wojewodztwoNazwa = selectedOption.value || selectedOption.textContent.trim();
                    
                    console.log('Wybrano województwo:', {
                        kod: wojewodztwoKod,
                        nazwa: wojewodztwoNazwa,
                        value: selectedOption.value
                    });
                    
                    // Resetuj powiat i miejscowość
                    powiatSelect.innerHTML = '<option value="">-- Wybierz powiat --</option>';
                    powiatSelect.disabled = !wojewodztwoNazwa;
                    miejscowoscSelect.innerHTML = '<option value="">-- Wybierz miejscowość --</option>';
                    miejscowoscSelect.disabled = true;
                    
                    if (wojewodztwoNazwa && wojewodztwoNazwa !== '') {
                        // Pobierz powiaty dla wybranego województwa
                        powiatSelect.disabled = true;
                        powiatSelect.innerHTML = '<option value="">Ładowanie...</option>';
                        
                        const url = `{{ route('rspo.api.powiaty') }}?wojewodztwo_kod=${encodeURIComponent(wojewodztwoKod || '')}&wojewodztwo_nazwa=${encodeURIComponent(wojewodztwoNazwa)}`;
                        console.log('Fetching powiaty from:', url);
                        
                        fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Powiaty response:', data);
                            if (data.success && data.powiaty && Array.isArray(data.powiaty)) {
                                powiatSelect.innerHTML = '<option value="">-- Wybierz powiat --</option>';
                                if (data.powiaty.length > 0) {
                                    data.powiaty.forEach(powiat => {
                                        const option = document.createElement('option');
                                        option.value = powiat.nazwa || '';
                                        option.setAttribute('data-kod', powiat.kod || '');
                                        option.setAttribute('data-woj-kod', powiat.wojewodztwo_kod || wojewodztwoKod || '');
                                        option.textContent = powiat.nazwa || 'Brak nazwy';
                                        powiatSelect.appendChild(option);
                                    });
                                    powiatSelect.disabled = false;
                                } else {
                                    powiatSelect.innerHTML = '<option value="">Brak powiatów dla wybranego województwa</option>';
                                }
                            } else {
                                console.error('Invalid response format:', data);
                                powiatSelect.innerHTML = '<option value="">Błąd podczas pobierania powiatów</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Error loading powiaty:', error);
                            powiatSelect.innerHTML = '<option value="">Błąd podczas pobierania powiatów</option>';
                        });
                    }
                });
            }

            // Obsługa zmiany powiatu
            if (powiatSelect) {
                powiatSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const powiatKod = selectedOption.getAttribute('data-kod');
                    const wojewodztwoKod = selectedOption.getAttribute('data-woj-kod');
                    const powiatNazwa = selectedOption.value || selectedOption.textContent.trim();
                    
                    console.log('Wybrano powiat:', {
                        kod: powiatKod,
                        nazwa: powiatNazwa,
                        wojewodztwo_kod: wojewodztwoKod
                    });
                    
                    // Resetuj miejscowość
                    miejscowoscSelect.innerHTML = '<option value="">-- Wybierz miejscowość --</option>';
                    miejscowoscSelect.disabled = !powiatNazwa || powiatNazwa === '';
                    
                    if (powiatNazwa && powiatNazwa !== '') {
                        // Pobierz miejscowości dla wybranego powiatu
                        miejscowoscSelect.disabled = true;
                        miejscowoscSelect.innerHTML = '<option value="">Ładowanie...</option>';
                        
                        const url = `{{ route('rspo.api.miejscowosci') }}?wojewodztwo_kod=${encodeURIComponent(wojewodztwoKod || '')}&powiat_kod=${encodeURIComponent(powiatKod || '')}&powiat_nazwa=${encodeURIComponent(powiatNazwa)}`;
                        console.log('Fetching miejscowosci from:', url);
                        console.log('Powiat kod:', powiatKod, 'Powiat nazwa:', powiatNazwa);
                        
                        fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Miejscowosci response:', data);
                            if (data.success && data.miejscowosci && Array.isArray(data.miejscowosci)) {
                                miejscowoscSelect.innerHTML = '<option value="">-- Wybierz miejscowość --</option>';
                                if (data.miejscowosci.length > 0) {
                                    data.miejscowosci.forEach(miejscowosc => {
                                        const option = document.createElement('option');
                                        option.value = miejscowosc.nazwa || '';
                                        option.textContent = miejscowosc.nazwa || 'Brak nazwy';
                                        miejscowoscSelect.appendChild(option);
                                    });
                                    miejscowoscSelect.disabled = false;
                                } else {
                                    miejscowoscSelect.innerHTML = '<option value="">Brak miejscowości dla wybranego powiatu</option>';
                                }
                            } else {
                                console.error('Invalid response format:', data);
                                miejscowoscSelect.innerHTML = '<option value="">Błąd podczas pobierania miejscowości</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Error loading miejscowosci:', error);
                            miejscowoscSelect.innerHTML = '<option value="">Błąd podczas pobierania miejscowości</option>';
                        });
                    }
                });
            }
        });
    </script>
    @endpush

</x-app-layout>
