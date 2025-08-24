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

            {{-- ======================  Tabela  ====================== --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Zamówienia z formularza</h5>
                    <div class="text-muted">
                        Wyświetlanie {{ $zamowienia->firstItem() ?? 0 }}-{{ $zamowienia->lastItem() ?? 0 }} z {{ $zamowienia->total() }} rekordów
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th style="width: 150px;">Data</th>
                                    <th style="width: 200px;">Imię i nazwisko</th>
                                    <th style="width: 200px;">Email</th>
                                    <th style="width: 150px;">Telefon</th>
                                    <th style="width: 200px;">Produkt</th>
                                    <th style="width: 100px;">Cena</th>
                                    <th style="width: 150px;">Nr faktury</th>
                                    <th style="width: 200px;">Notatki</th>
                                    <th style="width: 100px;">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($zamowienia as $zamowienie)
                                    <tr>
                                        <td>{{ $zamowienie->id }}</td>
                                        <td>
                                            @if($zamowienie->data_zamowienia)
                                                {{ \Carbon\Carbon::parse($zamowienie->data_zamowienia)->format('d.m.Y H:i') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $zamowienie->konto_imie_nazwisko ?? '—' }}</strong>
                                        </td>
                                        <td>
                                            <a href="mailto:{{ $zamowienie->konto_email ?? '' }}">{{ $zamowienie->konto_email ?? '—' }}</a>
                                        </td>
                                        <td>
                                            @if($zamowienie->zam_tel)
                                                <a href="tel:{{ $zamowienie->zam_tel }}">{{ $zamowienie->zam_tel }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $zamowienie->produkt_nazwa ?? '—' }}</td>
                                        <td>
                                            @if($zamowienie->produkt_cena)
                                                {{ number_format($zamowienie->produkt_cena, 2) }} zł
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($zamowienie->nr_fakury)
                                                <span class="badge bg-info">{{ $zamowienie->nr_fakury }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($zamowienie->notatki)
                                                <span class="badge bg-secondary" title="{{ $zamowienie->notatki }}">
                                                    <i class="bi bi-sticky"></i> {{ Str::limit($zamowienie->notatki, 30) }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('sales.show', $zamowienie->id) }}" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Szczegóły">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-inbox fs-1"></i>
                                                <p class="mt-2">Brak zamówień do wyświetlenia.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
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
