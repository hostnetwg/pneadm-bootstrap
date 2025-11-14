<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-person me-2"></i>
                Logi aktywności użytkownika: {{ $user->name }}
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
        {{-- Informacje o użytkowniku --}}
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-person-circle me-2"></i>
                    Informacje o użytkowniku
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Nazwa:</strong>
                            <span class="ms-2">{{ $user->name }}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong>
                            <span class="ms-2">{{ $user->email }}</span>
                        </div>
                        <div class="mb-3">
                            <strong>ID:</strong>
                            <span class="ms-2">#{{ $user->id }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Ostatnie logowanie:</strong>
                            <span class="ms-2">
                                @if($user->last_login_at)
                                    {{ $user->last_login_at->format('d.m.Y H:i:s') }}
                                    <small class="text-muted">({{ $user->last_login_at->diffForHumans() }})</small>
                                @else
                                    <span class="text-muted">Nigdy</span>
                                @endif
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Data utworzenia konta:</strong>
                            <span class="ms-2">
                                {{ $user->created_at->format('d.m.Y H:i:s') }}
                                <small class="text-muted">({{ $user->created_at->diffForHumans() }})</small>
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Ostatni IP:</strong>
                            <span class="ms-2">{{ $user->last_login_ip ?? 'Nieznany' }}</span>
                        </div>
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
                <form method="GET" action="{{ route('activity-logs.user-logs', $user->id) }}" class="row g-3">
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
                            <option value="login" {{ request('log_type') === 'login' ? 'selected' : '' }}>Logowanie</option>
                            <option value="logout" {{ request('log_type') === 'logout' ? 'selected' : '' }}>Wylogowanie</option>
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
                            <a href="{{ route('activity-logs.user-logs', $user->id) }}" class="btn btn-outline-secondary">
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
                    Logi aktywności użytkownika
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
                                    <th style="width: 10%;">Typ akcji</th>
                                    <th style="width: 40%;">Akcja</th>
                                    <th style="width: 15%;">Model</th>
                                    <th style="width: 10%;">IP</th>
                                    <th style="width: 5%;">Akcje</th>
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
                                            @if($log->model_type)
                                                <small class="badge bg-info">
                                                    {{ $log->model_type_short }}
                                                </small>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $log->ip_address ?? '—' }}</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('activity-logs.show', $log->id) }}" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Szczegóły">
                                                <i class="bi bi-eye"></i>
                                            </a>
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
                                Użytkownik {{ $user->name }} nie ma logów spełniających kryteria wyszukiwania.
                            @else
                                Użytkownik {{ $user->name }} nie ma jeszcze żadnych logów aktywności.
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

        {{-- Statystyki użytkownika --}}
        @if($logs->total() > 0)
            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart me-2"></i>
                        Statystyki użytkownika
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $userStats = $logs->groupBy('log_type');
                    @endphp
                    <div class="row">
                        @foreach($userStats as $type => $typeLogs)
                            @php
                                $typeNames = [
                                    'login' => 'Logowania',
                                    'logout' => 'Wylogowania',
                                    'create' => 'Utworzenia',
                                    'update' => 'Aktualizacje',
                                    'delete' => 'Usunięcia',
                                    'restore' => 'Przywrócenia',
                                    'view' => 'Wyświetlenia',
                                    'custom' => 'Niestandardowe',
                                ];
                                $typeColors = [
                                    'login' => 'success',
                                    'logout' => 'secondary',
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












