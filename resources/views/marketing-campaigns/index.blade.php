@php
    // Zmienne pomocnicze dla sortowania
    $currentSortBy = request('sort_by', 'created_at');
    $currentSortOrder = request('sort_order', 'desc');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Źródła pozyskania') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Kampanie marketingowe</h3>
                <a href="{{ route('marketing-campaigns.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Dodaj kampanię
                </a>
            </div>

            <!-- Formularz wyszukiwania i filtrów -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('marketing-campaigns.index') }}" class="row g-3">
                        <!-- Wyszukiwanie -->
                        <div class="col-md-4">
                            <label for="search" class="form-label">Wyszukaj</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="{{ request('search') }}" 
                                   placeholder="Kod, nazwa lub opis kampanii...">
                        </div>
                        
                        <!-- Filtr typu źródła -->
                        <div class="col-md-3">
                            <label for="source_type_id" class="form-label">Typ źródła</label>
                            <select class="form-select" id="source_type_id" name="source_type_id">
                                <option value="">Wszystkie typy</option>
                                @foreach($sourceTypes as $sourceType)
                                    <option value="{{ $sourceType->id }}" 
                                            {{ request('source_type_id') == $sourceType->id ? 'selected' : '' }}>
                                        {{ $sourceType->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Filtr statusu -->
                        <div class="col-md-2">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="">Wszystkie</option>
                                <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktywne</option>
                                <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Nieaktywne</option>
                            </select>
                        </div>
                        
                        <!-- Liczba na stronę -->
                        <div class="col-md-2">
                            <label for="per_page" class="form-label">Na stronę</label>
                            <select class="form-select" id="per_page" name="per_page">
                                <option value="10" {{ request('per_page') == '10' ? 'selected' : '' }}>10</option>
                                <option value="20" {{ request('per_page') == '20' ? 'selected' : '' }}>20</option>
                                <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                        
                        <!-- Przyciski -->
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @if($campaigns->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => $currentSortBy == 'created_at' && $currentSortOrder == 'asc' ? 'desc' : 'asc']) }}" 
                                       class="text-white text-decoration-none">
                                        Data utworzenia
                                        @if($currentSortBy == 'created_at')
                                            <i class="bi bi-arrow-{{ $currentSortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'campaign_code', 'sort_order' => $currentSortBy == 'campaign_code' && $currentSortOrder == 'asc' ? 'desc' : 'asc']) }}" 
                                       class="text-white text-decoration-none">
                                        Kod kampanii
                                        @if($currentSortBy == 'campaign_code')
                                            <i class="bi bi-arrow-{{ $currentSortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => $currentSortBy == 'name' && $currentSortOrder == 'asc' ? 'desc' : 'asc']) }}" 
                                       class="text-white text-decoration-none">
                                        Nazwa
                                        @if($currentSortBy == 'name')
                                            <i class="bi bi-arrow-{{ $currentSortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'source_type', 'sort_order' => $currentSortBy == 'source_type' && $currentSortOrder == 'asc' ? 'desc' : 'asc']) }}" 
                                       class="text-white text-decoration-none">
                                        Typ źródła
                                        @if($currentSortBy == 'source_type')
                                            <i class="bi bi-arrow-{{ $currentSortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'is_active', 'sort_order' => $currentSortBy == 'is_active' && $currentSortOrder == 'asc' ? 'desc' : 'asc']) }}" 
                                       class="text-white text-decoration-none">
                                        Status
                                        @if($currentSortBy == 'is_active')
                                            <i class="bi bi-arrow-{{ $currentSortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'orders_count', 'sort_order' => $currentSortBy == 'orders_count' && $currentSortOrder == 'asc' ? 'desc' : 'asc']) }}" 
                                       class="text-white text-decoration-none">
                                        Liczba zamówień
                                        @if($currentSortBy == 'orders_count')
                                            <i class="bi bi-arrow-{{ $currentSortOrder == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($campaigns as $campaign)
                                <tr>
                                    <td>
                                        @if($campaign->created_at)
                                            {{ $campaign->created_at->format('d.m.Y H:i') }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $campaign->campaign_code }}</code></td>
                                    <td>{{ $campaign->name }}</td>
                                    <td>
                                        @if($campaign->sourceType)
                                            <span class="badge" 
                                                  style="background-color: {{ $campaign->sourceType->color }}; color: white;"
                                                  title="{{ $campaign->sourceType->description ?? 'Brak opisu' }}"
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top">
                                                {{ $campaign->sourceType->name }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary" 
                                                  title="Typ źródła nie został określony"
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top">
                                                Nieznany
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($campaign->is_active)
                                            <span class="badge bg-success">Aktywna</span>
                                        @else
                                            <span class="badge bg-secondary">Nieaktywna</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $campaign->form_orders_count ?? $campaign->formOrders->count() }}</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('marketing-campaigns.show', $campaign) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Podgląd
                                            </a>
                                            <a href="{{ route('marketing-campaigns.edit', $campaign) }}" class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-pencil"></i> Edytuj
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal{{ $campaign->id }}">
                                                <i class="bi bi-trash"></i> Usuń
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Informacja o wynikach -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">
                        Wyświetlane {{ $campaigns->firstItem() ?? 0 }} - {{ $campaigns->lastItem() ?? 0 }} 
                        z {{ $campaigns->total() }} kampanii
                        @if(request()->hasAny(['search', 'source_type_id', 'is_active']))
                            <span class="badge bg-info ms-2">Filtrowane</span>
                        @endif
                    </div>
                    <div>
                        {{ $campaigns->links() }}
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-graph-up fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Brak kampanii marketingowych</h4>
                    <p class="text-muted">Dodaj pierwszą kampanię, aby rozpocząć śledzenie źródeł pozyskania.</p>
                    <a href="{{ route('marketing-campaigns.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Dodaj pierwszą kampanię
                    </a>
                </div>
            @endif

            {{-- Modale potwierdzenia usunięcia kampanii --}}
            @foreach ($campaigns as $campaign)
<div class="modal fade" id="deleteModal{{ $campaign->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $campaign->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel{{ $campaign->id }}">
                    <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Czy na pewno chcesz usunąć kampanię <strong>#{{ $campaign->id }}</strong>?</p>
                <div class="bg-light p-3 rounded">
                    <h6 class="mb-2">Szczegóły kampanii:</h6>
                    <ul class="mb-0">
                        <li><strong>Nazwa:</strong> {{ $campaign->name }}</li>
                        <li><strong>Typ źródła:</strong> {{ $campaign->sourceType->name ?? 'Brak' }}</li>
                        <li><strong>Opis:</strong> {{ $campaign->description ? Str::limit($campaign->description, 100) : 'Brak' }}</li>
                        <li><strong>Status:</strong> {{ $campaign->is_active ? 'Aktywna' : 'Nieaktywna' }}</li>
                        <li><strong>Data utworzenia:</strong> {{ $campaign->created_at ? $campaign->created_at->format('d.m.Y H:i') : 'Nieznana' }}</li>
                    </ul>
                </div>
                <p class="text-muted mt-3">
                    <i class="bi bi-info-circle"></i>
                    Kampania zostanie przeniesiona do kosza (soft delete) i będzie można ją przywrócić.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Anuluj
                </button>
                <form action="{{ route('marketing-campaigns.destroy', $campaign) }}" 
                      method="POST" 
                      class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Usuń kampanię
                    </button>
                </form>
            </div>
        </div>
    </div>
    </div>
    @endforeach
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicjalizacja tooltipów Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush
