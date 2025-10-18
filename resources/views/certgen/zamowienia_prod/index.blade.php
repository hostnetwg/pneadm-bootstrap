<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                {{ __('Produkty formularzy zamówień') }} <span class="text-primary">(zamowienia_PROD)</span>
            </h2>
            <a href="{{ route('certgen.zamowienia_prod.create') }}" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Dodaj nowy produkt
            </a>
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid">

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

            {{-- Wyszukiwanie --}}
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('certgen.zamowienia_prod.index') }}" class="row g-3">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Wyszukaj:</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="search" 
                                   name="search" 
                                   value="{{ $search }}" 
                                   placeholder="Nazwa produktu, promocja, ID Publigo, ID...">
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
                                    <a href="{{ route('certgen.zamowienia_prod.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Wyczyść
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tabela zamówień --}}
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>ID Publigo</th>
                                    <th>Nazwa produktu</th>
                                    <th>Promocja</th>
                                    <th>Status</th>
                                    <th>ID Ceny</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($zamowienia as $zamowienie)
                                    <tr>
                                        <td><strong>#{{ $zamowienie->id }}</strong></td>
                                        <td>
                                            @if($zamowienie->idProdPubligo)
                                                <code>{{ $zamowienie->idProdPubligo }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small>{{ Str::limit($zamowienie->nazwa ?? '—', 60) }}</small>
                                        </td>
                                        <td>
                                            @if($zamowienie->promocja)
                                                <span class="badge bg-warning text-dark">{{ $zamowienie->promocja }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($zamowienie->status)
                                                <span class="badge bg-{{ $zamowienie->status == 1 ? 'success' : 'secondary' }}">
                                                    {{ $zamowienie->status == 1 ? 'Aktywny' : 'Nieaktywny' }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($zamowienie->price_id_ProdPubligo)
                                                <code>{{ $zamowienie->price_id_ProdPubligo }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('certgen.zamowienia_prod.show', $zamowienie->id) }}" 
                                                   class="btn btn-sm btn-primary"
                                                   title="Szczegóły">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ route('certgen.zamowienia_prod.edit', $zamowienie->id) }}" 
                                                   class="btn btn-sm btn-warning"
                                                   title="Edytuj">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form action="{{ route('certgen.zamowienia_prod.destroy', $zamowienie->id) }}" 
                                                      method="POST" 
                                                      class="d-inline"
                                                      onsubmit="return confirm('Czy na pewno chcesz usunąć ten produkt wraz z wszystkimi wariantami cenowymi?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-danger"
                                                            title="Usuń">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <p class="text-muted mb-0">
                                                @if($search)
                                                    Nie znaleziono produktów dla frazy: <strong>"{{ $search }}"</strong>
                                                @else
                                                    Brak produktów do wyświetlenia
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginacja --}}
                    @if($zamowienia->hasPages())
                        <div class="mt-3">
                            {{ $zamowienia->appends(request()->query())->links() }}
                        </div>
                    @endif

                    {{-- Info o liczbie rekordów --}}
                    <div class="mt-3 text-muted small">
                        Wyświetlono {{ $zamowienia->firstItem() ?? 0 }} - {{ $zamowienia->lastItem() ?? 0 }} 
                        z {{ $zamowienia->total() }} rekordów
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

