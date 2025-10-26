<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-activity me-2"></i>
                Szczegóły logu aktywności #{{ $log->id }}
            </h2>
            <div class="btn-group" role="group">
                <a href="{{ $prevLog ? route('activity-logs.show', $prevLog->id) : '#' }}" 
                   class="btn {{ $prevLog ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}" 
                   title="{{ $prevLog ? 'Poprzedni log' : 'Brak poprzedniego logu' }}"
                   @if(!$prevLog) onclick="return false;" @endif>
                    <i class="bi bi-chevron-left"></i> Poprzedni
                </a>
                <a href="{{ route('activity-logs.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-list"></i> Lista
                </a>
                <a href="{{ $nextLog ? route('activity-logs.show', $nextLog->id) : '#' }}" 
                   class="btn {{ $nextLog ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}" 
                   title="{{ $nextLog ? 'Następny log' : 'Brak następnego logu' }}"
                   @if(!$nextLog) onclick="return false;" @endif>
                    Następny <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        {{-- Podstawowe informacje --}}
        <div class="card mb-4">
            <div class="card-header bg-{{ $log->log_type_color }} text-white">
                <h5 class="mb-0">
                    <i class="bi {{ $log->log_type_icon }} me-2"></i>
                    {{ $log->log_type_name }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Informacje podstawowe</h6>
                        
                        <div class="mb-3">
                            <strong>ID logu:</strong>
                            <span class="ms-2">#{{ $log->id }}</span>
                        </div>

                        <div class="mb-3">
                            <strong>Data i czas:</strong>
                            <span class="ms-2">
                                <i class="bi bi-clock"></i>
                                {{ $log->created_at->format('d.m.Y H:i:s') }}
                                <small class="text-muted">({{ $log->created_at->diffForHumans() }})</small>
                            </span>
                        </div>

                        <div class="mb-3">
                            <strong>Użytkownik:</strong>
                            <span class="ms-2">
                                @if($log->user)
                                    <a href="{{ route('activity-logs.user-logs', $log->user_id) }}" class="text-decoration-none">
                                        {{ $log->user->name }} ({{ $log->user->email }})
                                    </a>
                                @else
                                    <span class="text-muted">System</span>
                                @endif
                            </span>
                        </div>

                        <div class="mb-3">
                            <strong>Typ akcji:</strong>
                            <span class="ms-2">
                                <span class="badge bg-{{ $log->log_type_color }} text-white">
                                    <i class="bi {{ $log->log_type_icon }}"></i>
                                    {{ $log->log_type_name }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Dane techniczne</h6>

                        <div class="mb-3">
                            <strong>Adres IP:</strong>
                            <span class="ms-2">{{ $log->ip_address ?? 'Nieznany' }}</span>
                        </div>

                        <div class="mb-3">
                            <strong>Metoda HTTP:</strong>
                            <span class="ms-2">
                                @if($log->method)
                                    <span class="badge bg-dark">{{ $log->method }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </span>
                        </div>

                        <div class="mb-3">
                            <strong>URL:</strong>
                            <span class="ms-2">
                                @if($log->url)
                                    <small><code>{{ Str::limit($log->url, 80) }}</code></small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </span>
                        </div>

                        <div class="mb-3">
                            <strong>User Agent:</strong>
                            <span class="ms-2">
                                @if($log->user_agent)
                                    <small class="text-muted">{{ Str::limit($log->user_agent, 80) }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Akcja i opis --}}
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-card-text me-2"></i>
                    Akcja
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Akcja:</strong>
                    <p class="mt-2">{{ $log->action }}</p>
                </div>

                @if($log->description)
                    <div class="mb-3">
                        <strong>Opis:</strong>
                        <p class="mt-2">{{ $log->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Model --}}
        @if($log->model_type)
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-database me-2"></i>
                        Dotyczący rekordu
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Typ modelu:</strong>
                        <span class="ms-2">
                            <span class="badge bg-info">{{ $log->model_type_short }}</span>
                            <small class="text-muted ms-2">({{ $log->model_type }})</small>
                        </span>
                    </div>

                    <div class="mb-3">
                        <strong>ID rekordu:</strong>
                        <span class="ms-2">#{{ $log->model_id }}</span>
                    </div>

                    @if($log->model_name)
                        <div class="mb-3">
                            <strong>Nazwa rekordu:</strong>
                            <span class="ms-2">{{ $log->model_name }}</span>
                        </div>
                    @endif

                    @if($log->model_type && $log->model_id)
                        <div class="mt-3">
                            <a href="{{ route('activity-logs.model-logs', [$log->model_type, $log->model_id]) }}" 
                               class="btn btn-sm btn-outline-info">
                                <i class="bi bi-list"></i> Zobacz wszystkie logi tego rekordu
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Zmiany (dla update) --}}
        @if($log->log_type === 'update' && ($log->old_values || $log->new_values))
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-left-right me-2"></i>
                        Zmiany
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">Pole</th>
                                    <th style="width: 37.5%;">Przed zmianą</th>
                                    <th style="width: 37.5%;">Po zmianie</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $oldValues = $log->old_values ?? [];
                                    $newValues = $log->new_values ?? [];
                                    $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
                                @endphp

                                @foreach($allFields as $field)
                                    @php
                                        $oldValue = $oldValues[$field] ?? null;
                                        $newValue = $newValues[$field] ?? null;
                                        
                                        // Formatowanie wartości
                                        $oldFormatted = is_null($oldValue) ? '<em class="text-muted">null</em>' : (is_bool($oldValue) ? ($oldValue ? 'true' : 'false') : htmlspecialchars($oldValue));
                                        $newFormatted = is_null($newValue) ? '<em class="text-muted">null</em>' : (is_bool($newValue) ? ($newValue ? 'true' : 'false') : htmlspecialchars($newValue));
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $field }}</strong></td>
                                        <td>
                                            <span class="text-danger">{!! $oldFormatted !!}</span>
                                        </td>
                                        <td>
                                            <span class="text-success">{!! $newFormatted !!}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Dane rekordu (dla create/delete) --}}
        @if(in_array($log->log_type, ['create', 'delete']) && ($log->new_values || $log->old_values))
            <div class="card mb-4">
                <div class="card-header bg-{{ $log->log_type === 'create' ? 'success' : 'danger' }} text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-{{ $log->log_type === 'create' ? 'plus' : 'trash' }} me-2"></i>
                        Dane rekordu
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $values = $log->log_type === 'create' ? ($log->new_values ?? []) : ($log->old_values ?? []);
                    @endphp

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;">Pole</th>
                                    <th style="width: 70%;">Wartość</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($values as $field => $value)
                                    <tr>
                                        <td><strong>{{ $field }}</strong></td>
                                        <td>
                                            @if(is_null($value))
                                                <em class="text-muted">null</em>
                                            @elseif(is_bool($value))
                                                {{ $value ? 'true' : 'false' }}
                                            @elseif(is_array($value))
                                                <code>{{ json_encode($value) }}</code>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>




