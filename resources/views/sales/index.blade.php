<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Nowe zamówienia') }} <span class="text-success">(CERTGEN)</span>
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">

            {{-- Komunikaty --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Przyciski filtrów --}}
            <div class="mb-3">
                <div class="btn-group" role="group">
                    <a href="{{ route('sales.index') }}" 
                       class="btn {{ $filter === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="bi bi-list"></i> Wszystkie
                    </a>
                    <a href="{{ route('sales.index', ['filter' => 'new']) }}" 
                       class="btn {{ $filter === 'new' ? 'btn-warning' : 'btn-outline-warning' }}">
                        <i class="bi bi-exclamation-triangle"></i> NOWE
                    </a>
                </div>
                
                @if($filter === 'new')
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-info-circle"></i> 
                            Pokazuję tylko niezakończone zamówienia bez numeru faktury
                        </span>
                    </div>
                @endif
            </div>

            {{-- Wyszukiwanie --}}
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('sales.index') }}" class="row g-3">
                        <input type="hidden" name="filter" value="{{ $filter }}">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Wyszukaj:</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="search" 
                                   name="search" 
                                   value="{{ $search }}" 
                                   placeholder="Imię, email, produkt, numer faktury, notatki, ID, Publigo ID...">
                        </div>
                        <div class="col-md-3">
                            <label for="per_page" class="form-label">Rekordów na stronę:</label>
                            <select id="per_page" name="per_page" class="form-select">
                                <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                <option value="200" {{ $perPage == 200 ? 'selected' : '' }}>200</option>
                                <option value="all" {{ $perPage == 'all' ? 'selected' : '' }}>Wszystkie</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Szukaj
                                </button>
                                @if($search)
                                    <a href="{{ route('sales.index', ['filter' => $filter]) }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Wyczyść
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Statystyki --}}
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Wszystkie zamówienia</h5>
                            <h3 class="card-text">{{ $zamowienia->total() }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Dzisiaj</h5>
                            <h3 class="card-text">{{ DB::connection('mysql_certgen')->table('zamowienia_FORM')->where('data_zamowienia', '>=', \Carbon\Carbon::today())->count() }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Wartość sprzedaży</h5>
                            <h3 class="card-text">{{ number_format(DB::connection('mysql_certgen')->table('zamowienia_FORM')->whereNotNull('nr_fakury')->where('nr_fakury', '!=', '')->where('nr_fakury', '!=', '0')->sum('produkt_cena'), 2) }} zł</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h5 class="card-title">Średnia cena</h5>
                            <h3 class="card-text">{{ DB::connection('mysql_certgen')->table('zamowienia_FORM')->whereNotNull('nr_fakury')->where('nr_fakury', '!=', '')->where('nr_fakury', '!=', '0')->avg('produkt_cena') ? number_format(DB::connection('mysql_certgen')->table('zamowienia_FORM')->whereNotNull('nr_fakury')->where('nr_fakury', '!=', '')->where('nr_fakury', '!=', '0')->avg('produkt_cena'), 2) : '0.00' }} zł</h3>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ======================  Lista zamówień w stylu dokumentu  ====================== --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Zamówienia z formularza</h5>
                    <div class="text-muted">
                        Wyświetlanie {{ $zamowienia->firstItem() ?? 0 }}-{{ $zamowienia->lastItem() ?? 0 }} z {{ $zamowienia->total() }} rekordów
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($zamowienia as $zamowienie)
                        @php
                            $isNew = (!$zamowienie->nr_fakury || $zamowienie->nr_fakury == '' || $zamowienie->nr_fakury == '0') && $zamowienie->status_zakonczone == 0;
                            $isCompleted = $zamowienie->status_zakonczone == 1;
                            $hasInvoice = $zamowienie->nr_fakury && $zamowienie->nr_fakury != '' && $zamowienie->nr_fakury != '0';
                        @endphp
                        
                        <div class="card shadow-sm mb-4 @if($isNew) border-warning @elseif($isCompleted) border-secondary @else border-primary @endif">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">
                                            <span class="badge bg-dark fs-6">ID: #{{ $zamowienie->id }}</span>
                                            @if($zamowienie->idProdPubligo)
                                                <span class="badge bg-info fs-6 ms-2">Publigo ID: #{{ $zamowienie->idProdPubligo }}</span>
                                            @endif
                                            @if($isNew)
                                                <span class="badge bg-warning text-dark ms-2">
                                                    <i class="bi bi-exclamation-triangle"></i> NOWE
                                                </span>
                                            @elseif($isCompleted)
                                                <span class="badge bg-secondary ms-2">
                                                    <i class="bi bi-check-circle"></i> ZAKOŃCZONE
                                                </span>
                                            @elseif($hasInvoice)
                                                <span class="badge bg-success ms-2">
                                                    <i class="bi bi-receipt"></i> FAKTURA
                                                </span>
                                            @endif
                                        </h5>
                                        @if($zamowienie->data_zamowienia)
                                            <small class="text-muted">
                                                <i class="bi bi-calendar-event"></i> {{ \Carbon\Carbon::parse($zamowienie->data_zamowienia)->format('d.m.Y H:i') }}
                                            </small>
                                        @endif
                                    </div>
                                    <div class="text-end">
                                        <a href="{{ route('sales.show', $zamowienie->id) }}" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="Szczegóły zamówienia">
                                            <i class="bi bi-eye"></i> Szczegóły
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                {{-- SZKOLENIE --}}
                                <div class="mb-4">
                                    <h6 class="text-primary fw-bold mb-2">
                                        <i class="bi bi-calendar-event"></i> SZKOLENIE
                                    </h6>
                                    <div class="fs-5 fw-semibold text-dark">{{ $zamowienie->produkt_nazwa ?? '—' }}</div>
                                    @if($zamowienie->produkt_cena)
                                        <div class="text-success fw-bold fs-6">
                                            {{ number_format($zamowienie->produkt_cena, 2) }} PLN
                                        </div>
                                    @endif
                                </div>

                                {{-- Telefon --}}
                                @if($zamowienie->zam_tel)
                                    <div class="mb-3">
                                        <h6 class="text-dark fw-bold mb-1">
                                            <i class="bi bi-telephone"></i> KONTAKT
                                        </h6>
                                        <div class="text-dark">tel. {{ $zamowienie->zam_tel }}</div>
                                    </div>
                                @endif

                                <div class="row">
                                    {{-- NABYWCA --}}
                                    <div class="col-md-6 mb-3">
                                        <h6 class="text-primary fw-bold mb-2">
                                            <i class="bi bi-building"></i> NABYWCA
                                        </h6>
                                        <div class="text-dark">
                                            <div class="fw-semibold">{{ $zamowienie->nab_nazwa ?? '—' }}</div>
                                            <div>{{ $zamowienie->nab_adres ?? '—' }}</div>
                                            <div>{{ $zamowienie->nab_kod ?? '—' }} {{ $zamowienie->nab_poczta ?? '—' }}</div>
                                            @if($zamowienie->nab_nip)
                                                <div class="text-primary fw-semibold">NIP: {{ preg_replace('/[^0-9]/', '', $zamowienie->nab_nip) }}</div>
                                            @endif
                                        </div>
                                        <div class="mt-2">
                                            @if($zamowienie->nab_nip)
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyNipNabywcy_{{ $zamowienie->id }}(this)">
                                                    <i class="bi bi-clipboard"></i> NIP
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ODBIORCA --}}
                                    <div class="col-md-6 mb-3">
                                        <h6 class="text-success fw-bold mb-2">
                                            <i class="bi bi-geo-alt"></i> ODBIORCA
                                        </h6>
                                        <div class="text-dark">
                                            <div class="fw-semibold">{{ $zamowienie->odb_nazwa ?? '—' }}</div>
                                            <div>{{ $zamowienie->odb_adres ?? '—' }}</div>
                                            <div>{{ $zamowienie->odb_kod ?? '—' }} {{ $zamowienie->odb_poczta ?? '—' }}</div>
                                            <div class="text-primary fw-semibold">nowoczesna-edukacja.pl</div>
                                        </div>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyOdbiorcaData_{{ $zamowienie->id }}(this)">
                                                <i class="bi bi-clipboard"></i> ODBIORCA
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- FAKTURA --}}
                                <div class="mb-3">
                                    <h6 class="text-info fw-bold mb-2">
                                        <i class="bi bi-receipt"></i> FAKTURA
                                    </h6>
                                    
                                    {{-- Informacje o fakturze --}}
                                    @if($hasInvoice)
                                        <div class="text-dark mb-1">
                                            <span class="fw-semibold">Nr faktury:</span> {{ $zamowienie->nr_fakury }}
                                        </div>
                                    @else
                                        <div class="text-warning mb-1">
                                            <i class="bi bi-clock"></i> Oczekuje na wystawienie faktury
                                        </div>
                                    @endif
                                    @if($zamowienie->zam_email)
                                        <div class="text-dark">
                                            <span class="fw-semibold">Email:</span> 
                                            <a href="mailto:{{ $zamowienie->zam_email }}" class="text-primary text-decoration-none">
                                                {{ $zamowienie->zam_email }}
                                            </a>
                                        </div>
                                        <div class="mt-1">
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="copyEmailFaktury_{{ $zamowienie->id }}(this)">
                                                <i class="bi bi-clipboard"></i> Email faktury
                                            </button>
                                        </div>
                                    @endif
                                    @if($zamowienie->faktura_uwagi)
                                        <div class="text-danger mt-1">
                                            <span class="fw-semibold">Uwagi:</span> {{ $zamowienie->faktura_uwagi }}
                                        </div>
                                    @endif
                                    @if($zamowienie->faktura_odroczenie)
                                        <div class="text-danger">
                                            <span class="fw-semibold">Odroczenie:</span> {{ $zamowienie->faktura_odroczenie }} dni
                                        </div>
                                    @endif
                                </div>

                                {{-- UCZESTNIK --}}
                                <div class="mb-3">
                                    <h6 class="text-dark fw-bold mb-2">
                                        <i class="bi bi-person"></i> UCZESTNIK
                                    </h6>
                                    <div class="text-dark">
                                        <div class="fw-semibold">{{ $zamowienie->konto_imie_nazwisko ?? '—' }}</div>
                                        @if($zamowienie->konto_email)
                                            <a href="mailto:{{ $zamowienie->konto_email }}" class="text-primary text-decoration-none">
                                                {{ $zamowienie->konto_email }}
                                            </a>
                                        @endif
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-info btn-sm" onclick="copyUczestnik_{{ $zamowienie->id }}(this)">
                                            <i class="bi bi-clipboard"></i> Uczestnik
                                        </button>
                                        @if($zamowienie->konto_email)
                                            <button type="button" class="btn btn-outline-info btn-sm" onclick="copyEmailUczestnika_{{ $zamowienie->id }}(this)">
                                                <i class="bi bi-clipboard"></i> Email uczestnika
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Notatki --}}
                                @if($zamowienie->notatki)
                                    <div class="mb-3">
                                        <h6 class="text-secondary fw-bold mb-2">
                                            <i class="bi bi-sticky"></i> NOTATKI
                                        </h6>
                                        <div class="text-dark">{{ $zamowienie->notatki }}</div>
                                    </div>
                                @endif

                                {{-- Formularz edycji na końcu karty --}}
                                <div class="mt-4 pt-3 border-top">
                                    <h6 class="text-dark fw-bold mb-3">
                                        <i class="bi bi-pencil"></i> EDYCJA ZAMÓWIENIA
                                    </h6>
                                    <form method="POST" action="{{ route('sales.update', $zamowienie->id) }}" class="mb-3">
                                        @csrf
                                        @method('PUT')
                                        {{-- Ukryte pola dla zachowania parametrów URL --}}
                                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                                        <input type="hidden" name="search" value="{{ $search }}">
                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                        <input type="hidden" name="page" value="{{ request()->get('page', 1) }}">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="nr_fakury_{{ $zamowienie->id }}" class="form-label small">Nr faktury:</label>
                                                <input type="text" 
                                                       class="form-control form-control-sm @if((!$zamowienie->nr_fakury || $zamowienie->nr_fakury == '' || $zamowienie->nr_fakury == '0') && $zamowienie->status_zakonczone == 0) border-danger bg-danger bg-opacity-10 @endif"
                                                       id="nr_fakury_{{ $zamowienie->id }}" 
                                                       name="nr_fakury"
                                                       value="{{ $zamowienie->nr_fakury }}"
                                                       placeholder="Wprowadź numer faktury"
                                                       @if((!$zamowienie->nr_fakury || $zamowienie->nr_fakury == '' || $zamowienie->nr_fakury == '0') && $zamowienie->status_zakonczone == 0)
                                                       style="border-width: 2px; box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);"
                                                       @endif>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">Status:</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="status_zakonczone_{{ $zamowienie->id }}" name="status_zakonczone" value="1" {{ $zamowienie->status_zakonczone == 1 ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="status_zakonczone_{{ $zamowienie->id }}">
                                                        Zakończone
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-2">
                                            <div class="col-12">
                                                <label for="notatki_{{ $zamowienie->id }}" class="form-label small">Notatki:</label>
                                                <textarea class="form-control form-control-sm" 
                                                          id="notatki_{{ $zamowienie->id }}" 
                                                          name="notatki" 
                                                          rows="2" 
                                                          placeholder="Dodaj notatki...">{{ $zamowienie->notatki }}</textarea>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="bi bi-check-circle"></i> Zapisz zmiany
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-3 mb-0">Brak zamówień do wyświetlenia.</p>
                                <small>Dostosuj filtry lub wyszukiwanie</small>
                            </div>
                        </div>
                    @endforelse
                </div>
                    
                    {{-- Paginacja --}}
                    @if($zamowienia->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    Strona {{ $zamowienia->currentPage() }} z {{ $zamowienia->lastPage() }}
                                </div>
                                <div>
                                    {{ $zamowienia->appends(['per_page' => $perPage, 'search' => $search, 'filter' => $filter])->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- JavaScript dla kopiowania danych --}}
    <script>
        // Funkcje pomocnicze
        function copyToClipboard(text, clickedButton) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopySuccess(clickedButton);
                }).catch(() => {
                    fallbackCopyTextToClipboard(text, clickedButton);
                });
            } else {
                fallbackCopyTextToClipboard(text, clickedButton);
            }
        }

        function showCopySuccess(clickedButton) {
            const originalText = clickedButton.innerHTML;
            clickedButton.innerHTML = '<i class="bi bi-check"></i> Skopiowano!';
            clickedButton.classList.remove('btn-outline-primary', 'btn-outline-secondary', 'btn-outline-info', 'btn-outline-warning');
            clickedButton.classList.add('btn-success');
            
            setTimeout(() => {
                clickedButton.innerHTML = originalText;
                clickedButton.classList.remove('btn-success');
                if (originalText.includes('NIP')) {
                    clickedButton.classList.add('btn-outline-secondary');
                } else if (originalText.includes('ODBIORCA')) {
                    clickedButton.classList.add('btn-outline-primary');
                } else if (originalText.includes('Email faktury')) {
                    clickedButton.classList.add('btn-outline-warning');
                } else {
                    clickedButton.classList.add('btn-outline-info');
                }
            }, 2000);
        }

        function fallbackCopyTextToClipboard(text, clickedButton) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.top = '0';
            textArea.style.left = '0';
            textArea.style.position = 'fixed';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCopySuccess(clickedButton);
            } catch (err) {
                console.error('Fallback: Nie udało się skopiować', err);
            }
            
            document.body.removeChild(textArea);
        }

        // Funkcje kopiowania dla każdego zamówienia
        @foreach($zamowienia as $zamowienie)
            function copyOdbiorcaData_{{ $zamowienie->id }}(button) {
                const odbiorcaData = `ODBIORCA: {{ $zamowienie->odb_nazwa ?? '' }}
{{ $zamowienie->odb_adres ?? '' }}
{{ $zamowienie->odb_kod ?? '' }} {{ $zamowienie->odb_poczta ?? '' }}
nowoczesna-edukacja.pl `;
                copyToClipboard(odbiorcaData, button);
            }

            function copyNipNabywcy_{{ $zamowienie->id }}(button) {
                const nip = '{{ preg_replace('/[^0-9]/', '', $zamowienie->nab_nip ?? '') }}';
                copyToClipboard(nip, button);
            }

            function copyUczestnik_{{ $zamowienie->id }}(button) {
                const uczestnik = '{{ $zamowienie->konto_imie_nazwisko ?? '' }}';
                copyToClipboard(uczestnik, button);
            }

            function copyEmailUczestnika_{{ $zamowienie->id }}(button) {
                const email = '{{ $zamowienie->konto_email ?? '' }}';
                copyToClipboard(email, button);
            }

            function copyEmailFaktury_{{ $zamowienie->id }}(button) {
                const email = '{{ $zamowienie->zam_email ?? '' }}';
                copyToClipboard(email, button);
            }
        @endforeach
    </script>

</x-app-layout>
