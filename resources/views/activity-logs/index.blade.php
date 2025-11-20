<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-activity me-2"></i>
                Logi aktywności
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('activity-logs.statistics') }}" class="btn btn-info">
                    <i class="bi bi-bar-chart"></i> Statystyki
                </a>
                <a href="{{ route('activity-logs.export', request()->query()) }}" class="btn btn-success">
                    <i class="bi bi-download"></i> Eksport CSV
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Filtry --}}
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-funnel"></i> Filtry wyszukiwania
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('activity-logs.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Wyszukaj</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="{{ $search }}" 
                               placeholder="Akcja, użytkownik, opis...">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="log_type" class="form-label">Typ akcji</label>
                        <select class="form-select" id="log_type" name="log_type">
                            <option value="">Wszystkie</option>
                            <option value="login" {{ $logType === 'login' ? 'selected' : '' }}>Logowanie</option>
                            <option value="logout" {{ $logType === 'logout' ? 'selected' : '' }}>Wylogowanie</option>
                            <option value="create" {{ $logType === 'create' ? 'selected' : '' }}>Utworzenie</option>
                            <option value="update" {{ $logType === 'update' ? 'selected' : '' }}>Aktualizacja</option>
                            <option value="delete" {{ $logType === 'delete' ? 'selected' : '' }}>Usunięcie</option>
                            <option value="restore" {{ $logType === 'restore' ? 'selected' : '' }}>Przywrócenie</option>
                            <option value="view" {{ $logType === 'view' ? 'selected' : '' }}>Wyświetlenie</option>
                            <option value="custom" {{ $logType === 'custom' ? 'selected' : '' }}>Niestandardowa</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="user_id" class="form-label">Użytkownik</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">Wszyscy</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ $userId == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="model_type" class="form-label">Model</label>
                        <select class="form-select" id="model_type" name="model_type">
                            <option value="">Wszystkie</option>
                            @foreach($modelTypes as $type)
                                <option value="{{ $type['value'] }}" {{ $modelType === $type['value'] ? 'selected' : '' }}>
                                    {{ $type['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Data od</label>
                        <input type="date" 
                               class="form-control" 
                               id="date_from" 
                               name="date_from" 
                               value="{{ $dateFrom }}">
                    </div>

                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Data do</label>
                        <input type="date" 
                               class="form-control" 
                               id="date_to" 
                               name="date_to" 
                               value="{{ $dateTo }}">
                    </div>

                    <div class="col-md-2">
                        <label for="per_page" class="form-label">Na stronie</label>
                        <select class="form-select" id="per_page" name="per_page">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                            <option value="all" {{ $perPage === 'all' ? 'selected' : '' }}>Wszystkie</option>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Szukaj
                            </button>
                            <a href="{{ route('activity-logs.index') }}" class="btn btn-outline-secondary">
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
                    Lista logów aktywności
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
                                    <th style="width: 12%;">Data/czas</th>
                                    <th style="width: 12%;">Użytkownik</th>
                                    <th style="width: 10%;">Typ akcji</th>
                                    <th style="width: 35%;">Akcja</th>
                                    <th style="width: 10%;">Model</th>
                                    <th style="width: 10%;">IP</th>
                                    <th style="width: 6%;">Akcje</th>
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
                            @if($search || $logType || $userId || $modelType || $dateFrom || $dateTo)
                                Nie znaleziono logów spełniających kryteria wyszukiwania.
                            @else
                                Jeszcze nie ma żadnych logów aktywności w systemie.
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
    </div>
</x-app-layout>














