<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-database me-2"></i>
                Logi aktywności rekordu: {{ $modelName ?? 'Nieznany' }}
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('activity-logs.index') }}" class="btn btn-primary">
                    <i class="bi bi-list"></i> Wszystkie logi
                </a>
                <a href="{{ route('activity-logs.statistics') }}" class="btn btn-info">
                    <i class="bi bi-bar-chart"></i> Statystyki
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        {{-- Informacje o rekordzie --}}
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-database me-2"></i>
                    Informacje o rekordzie
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Typ modelu:</strong>
                            <span class="ms-2">
                                <span class="badge bg-info">{{ $modelTypeShort }}</span>
                                <small class="text-muted ms-2">({{ $modelType }})</small>
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>ID rekordu:</strong>
                            <span class="ms-2">#{{ $modelId }}</span>
                        </div>
                        @if($modelName)
                            <div class="mb-3">
                                <strong>Nazwa rekordu:</strong>
                                <span class="ms-2">{{ $modelName }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Łączna liczba logów:</strong>
                            <span class="ms-2">{{ $logs->total() }}</span>
                        </div>
                        @if($logs->count() > 0)
                            <div class="mb-3">
                                <strong>Pierwszy log:</strong>
                                <span class="ms-2">{{ $logs->first()->created_at->format('d.m.Y H:i:s') }}</span>
                            </div>
                            <div class="mb-3">
                                <strong>Ostatni log:</strong>
                                <span class="ms-2">{{ $logs->last()->created_at->format('d.m.Y H:i:s') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Filtry --}}
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-funnel"></i> Filtry wyszukiwania
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('activity-logs.model-logs', [$modelType, $modelId]) }}" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Wyszukaj</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="{{ request('search', '') }}" 
                               placeholder="Akcja, opis...">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="log_type" class="form-label">Typ akcji</label>
                        <select class="form-select" id="log_type" name="log_type">
                            <option value="">Wszystkie</option>
                            <option value="create" {{ request('log_type') === 'create' ? 'selected' : '' }}>Utworzenie</option>
                            <option value="update" {{ request('log_type') === 'update' ? 'selected' : '' }}>Aktualizacja</option>
                            <option value="delete" {{ request('log_type') === 'delete' ? 'selected' : '' }}>Usunięcie</option>
                            <option value="restore" {{ request('log_type') === 'restore' ? 'selected' : '' }}>Przywrócenie</option>
                            <option value="view" {{ request('log_type') === 'view' ? 'selected' : '' }}>Wyświetlenie</option>
                            <option value="custom" {{ request('log_type') === 'custom' ? 'selected' : '' }}>Niestandardowa</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="per_page" class="form-label">Na stronie</label>
                        <select class="form-select" id="per_page" name="per_page">
                            <option value="10" {{ request('per_page', 25) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page', 25) == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page', 25) == 100 ? 'selected' : '' }}>100</option>
                            <option value="all" {{ request('per_page', 25) === 'all' ? 'selected' : '' }}>Wszystkie</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Szukaj
                            </button>
                            <a href="{{ route('activity-logs.model-logs', [$modelType, $modelId]) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Lista logów --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list me-2"></i>
                    Historia zmian rekordu
                    <span class="badge bg-secondary">{{ $logs->total() }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                @if($logs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">ID</th>
                                    <th style="width: 15%;">Data/czas</th>
                                    <th style="width: 12%;">Użytkownik</th>
                                    <th style="width: 10%;">Typ akcji</th>
                                    <th style="width: 35%;">Akcja</th>
                                    <th style="width: 10%;">IP</th>
                                    <th style="width: 13%;">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    <tr>
                                        <td>
                                            <small class="text-muted">#{{ $log->id }}</small>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="bi bi-clock"></i>
                                                {{ $log->created_at->format('d.m.Y H:i') }}
                                            </small>
                                        </td>
                                        <td>
                                            @if($log->user)
                                                <a href="{{ route('activity-logs.user-logs', $log->user_id) }}" 
                                                   class="text-decoration-none"
                                                   title="Zobacz wszystkie logi użytkownika">
                                                    <small>{{ $log->user->name }}</small>
                                                </a>
                                            @else
                                                <small class="text-muted">System</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $log->log_type_color }} text-white">
                                                <i class="bi {{ $log->log_type_icon }}"></i>
                                                {{ $log->log_type_name }}
                                            </span>
                                        </td>
                                        <td>
                                            <small>{{ Str::limit($log->action, 70) }}</small>
                                            @if($log->model_name)
                                                <br><small class="text-muted">{{ Str::limit($log->model_name, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $log->ip_address ?? '—' }}</small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('activity-logs.show', $log->id) }}" 
                                                   class="btn btn-outline-primary"
                                                   title="Szczegóły">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                @if($log->user)
                                                    <a href="{{ route('activity-logs.user-logs', $log->user_id) }}" 
                                                       class="btn btn-outline-info"
                                                       title="Logi użytkownika">
                                                        <i class="bi bi-person"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">Brak logów aktywności</h5>
                        <p class="text-muted">
                            @if(request('search') || request('log_type'))
                                Ten rekord nie ma logów spełniających kryteria wyszukiwania.
                            @else
                                Ten rekord nie ma jeszcze żadnych logów aktywności.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Paginacja --}}
        @if($logs->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $logs->appends(request()->query())->links() }}
            </div>
        @endif

        {{-- Statystyki rekordu --}}
        @if($logs->total() > 0)
            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart me-2"></i>
                        Statystyki rekordu
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $recordStats = $logs->groupBy('log_type');
                    @endphp
                    <div class="row">
                        @foreach($recordStats as $type => $typeLogs)
                            @php
                                $typeNames = [
                                    'create' => 'Utworzenia',
                                    'update' => 'Aktualizacje',
                                    'delete' => 'Usunięcia',
                                    'restore' => 'Przywrócenia',
                                    'view' => 'Wyświetlenia',
                                    'custom' => 'Niestandardowe',
                                ];
                                $typeColors = [
                                    'create' => 'primary',
                                    'update' => 'warning',
                                    'delete' => 'danger',
                                    'restore' => 'info',
                                    'view' => 'light',
                                    'custom' => 'dark',
                                ];
                            @endphp
                            <div class="col-md-3 mb-3">
                                <div class="card bg-{{ $typeColors[$type] ?? 'light' }} text-{{ $typeColors[$type] === 'light' ? 'dark' : 'white' }}">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">{{ $typeNames[$type] ?? $type }}</h6>
                                        <h4 class="mb-0">{{ $typeLogs->count() }}</h4>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>




