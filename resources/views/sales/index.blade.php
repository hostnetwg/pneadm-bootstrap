<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Nowe zamówienia') }}
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
                                   placeholder="Imię, email, produkt, numer faktury, notatki, ID...">
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
                                    </div>
                                </div>

                                {{-- FAKTURA --}}
                                <div class="mb-3">
                                    <h6 class="text-info fw-bold mb-2">
                                        <i class="bi bi-receipt"></i> FAKTURA
                                    </h6>
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
                                    @endif
                                    @if($zamowienie->faktura_uwagi)
                                        <div class="text-dark mt-1">
                                            <span class="fw-semibold">Uwagi:</span> {{ $zamowienie->faktura_uwagi }}
                                        </div>
                                    @endif
                                    @if($zamowienie->faktura_odroczenie)
                                        <div class="text-dark">
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

</x-app-layout>
