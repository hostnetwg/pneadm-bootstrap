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
                        <table class="table table-hover align-middle table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">ID</th>
                                    <th style="width: 8%;">ID Publigo</th>
                                    <th style="width: 35%;">Nazwa produktu</th>
                                    <th style="width: 15%;">Promocja</th>
                                    <th style="width: 10%;">Status</th>
                                    <th style="width: 8%;">ID Ceny</th>
                                    <th style="width: 19%;">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($zamowienia as $zamowienie)
                                    <tr>
                                        <td><strong>#{{ $zamowienie->id }}</strong></td>
                                        <td>
                                            @if($zamowienie->idProdPubligo)
                                                <small><code>{{ $zamowienie->idProdPubligo }}</code></small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($zamowienie->idProdPubligo)
                                                <a href="https://zdalna-lekcja.pl/zamowienia/formularz/?idP={{ $zamowienie->idProdPubligo }}" 
                                                   target="_blank" 
                                                   rel="noopener noreferrer"
                                                   class="text-decoration-none"
                                                   title="{{ $zamowienie->nazwa }} - Otwórz formularz zamówienia w nowej zakładce">
                                                    <small>{{ Str::limit($zamowienie->nazwa ?? '—', 50) }}</small>
                                                    <i class="bi bi-box-arrow-up-right ms-1"></i>
                                                </a>
                                            @else
                                                <small class="text-muted">{{ Str::limit($zamowienie->nazwa ?? '—', 50) }}</small>
                                                <small class="text-danger d-block">(brak ID Publigo)</small>
                                            @endif
                                        </td>
                                        <td style="word-wrap: break-word; white-space: normal;">
                                            @if($zamowienie->promocja)
                                                <small><span class="badge bg-warning text-dark">{{ $zamowienie->promocja }}</span></small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($zamowienie->status)
                                                <small><span class="badge bg-{{ $zamowienie->status == 1 ? 'success' : 'secondary' }}">
                                                    {{ $zamowienie->status == 1 ? 'Aktywny' : 'Nieaktywny' }}
                                                </span></small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($zamowienie->price_id_ProdPubligo)
                                                <small><code>{{ $zamowienie->price_id_ProdPubligo }}</code></small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('certgen.zamowienia_prod.show', $zamowienie->id) }}" 
                                                   class="btn btn-primary"
                                                   title="Szczegóły">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ route('certgen.zamowienia_prod.edit', $zamowienie->id) }}" 
                                                   class="btn btn-warning"
                                                   title="Edytuj">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-danger"
                                                        title="Usuń"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal{{ $zamowienie->id }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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

    {{-- Modale potwierdzenia usunięcia produktów --}}
    @foreach ($zamowienia as $zamowienie)
    <div class="modal fade" id="deleteModal{{ $zamowienie->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $zamowienie->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel{{ $zamowienie->id }}">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć produkt <strong>#{{ $zamowienie->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły produktu:</h6>
                        <ul class="mb-0">
                            <li><strong>Nazwa:</strong> {{ $zamowienie->nazwa ?? 'Brak' }}</li>
                            <li><strong>ID Publigo:</strong> {{ $zamowienie->idProdPubligo ?? 'Brak' }}</li>
                            <li><strong>ID Ceny Publigo:</strong> {{ $zamowienie->price_id_ProdPubligo ?? 'Brak' }}</li>
                            <li><strong>Promocja:</strong> {{ $zamowienie->promocja ?? 'Brak' }}</li>
                            <li><strong>Status:</strong> {{ $zamowienie->status ? 'Aktywny' : 'Nieaktywny' }}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3">
                        <i class="bi bi-info-circle"></i>
                        Produkt zostanie usunięty wraz z wszystkimi wariantami cenowymi. Ta operacja jest nieodwracalna!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <form action="{{ route('certgen.zamowienia_prod.destroy', $zamowienie->id) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń produkt
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</x-app-layout>

