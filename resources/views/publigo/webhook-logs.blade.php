<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-clock-history me-2"></i>Logi Webhooków Publigo
        </h2>
    </x-slot>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1 text-dark">
                        <i class="bi bi-clock-history text-primary me-2"></i>Logi Webhooków
                    </h1>
                    <p class="text-muted mb-0">Historia wszystkich webhooków z Publigo.pl</p>
                </div>
                <div>
                    <a href="{{ route('publigo.webhooks') }}" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i>Powrót do zarządzania
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtry -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="card-title mb-0">
                <i class="bi bi-funnel me-2"></i>Filtry
            </h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Typ Logu</label>
                        <select name="type" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="received" {{ request('type') === 'received' ? 'selected' : '' }}>Otrzymane</option>
                            <option value="error" {{ request('type') === 'error' ? 'selected' : '' }}>Błędy</option>
                            <option value="success" {{ request('type') === 'success' ? 'selected' : '' }}>Sukces</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Data od</label>
                        <input type="date" 
                               name="date_from" 
                               value="{{ request('date_from') }}" 
                               class="form-control">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Data do</label>
                        <input type="date" 
                               name="date_to" 
                               value="{{ request('date_to') }}" 
                               class="form-control">
                    </div>
                    
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search me-1"></i>Filtruj
                        </button>
                        <a href="{{ route('publigo.webhooks.logs') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Wyczyść
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Logi -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-ul me-2"></i>Logi Webhooków
                </h5>
                <div class="badge bg-light text-dark">
                    {{ $logs->count() }} logów
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if($logs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Endpoint</th>
                                <th>IP</th>
                                <th>Metoda</th>
                                <th>Źródło</th>
                                <th class="text-center">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td>
                                        <small class="text-muted">
                                            {{ $log->created_at->format('d.m.Y H:i:s') }}
                                        </small>
                                    </td>
                                    <td>
                                        @if($log->success)
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Sukces
                                            </span>
                                        @else
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle me-1"></i>Błąd
                                            </span>
                                        @endif
                                        @if($log->status_code)
                                            <br><small class="text-muted">HTTP {{ $log->status_code }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <code class="small">{{ $log->endpoint }}</code>
                                    </td>
                                    <td>
                                        <small>{{ $log->ip_address ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $log->method }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $log->source }}</span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" 
                                                class="btn btn-outline-primary btn-sm btn-log-details"
                                                data-log-id="{{ $log->id }}">
                                            <i class="bi bi-eye me-1"></i>Szczegóły
                                        </button>
                                    </td>
                                </tr>
                                @if($log->error_message)
                                    <tr class="table-danger">
                                        <td colspan="8">
                                            <div class="p-2">
                                                <strong class="text-danger">Błąd:</strong>
                                                <span class="text-danger">{{ $log->error_message }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if($logs instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="card-footer">
                        {{ $logs->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted"></i>
                    <h5 class="text-muted mt-3">Brak logów webhooków</h5>
                    <p class="text-muted">Nie znaleziono żadnych logów spełniających kryteria wyszukiwania.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal szczegółów logu -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-text me-2"></i>Szczegóły webhooka
                    <span id="modalLogDate" class="ms-2 text-muted small"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <span id="modalLogStatus" class="badge"></span>
                    <span id="modalLogStatusCode" class="ms-2 text-muted small"></span>
                </div>
                <div id="modalLogError" class="alert alert-danger d-none mb-3"></div>
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-request">Request</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-response">Response</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-headers">Headers</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-user-agent">User-Agent</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-request">
                        <pre id="modalRequestData" class="bg-light p-3 rounded small" style="max-height: 400px; overflow: auto;"></pre>
                    </div>
                    <div class="tab-pane fade" id="tab-response">
                        <pre id="modalResponseData" class="bg-light p-3 rounded small" style="max-height: 400px; overflow: auto;"></pre>
                    </div>
                    <div class="tab-pane fade" id="tab-headers">
                        <pre id="modalHeadersData" class="bg-light p-3 rounded small" style="max-height: 400px; overflow: auto;"></pre>
                    </div>
                    <div class="tab-pane fade" id="tab-user-agent">
                        <pre id="modalUserAgent" class="bg-light p-3 rounded small"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh co 30 sekund
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);

// Obsługa przycisku Szczegóły - pobieranie danych przez AJAX
document.querySelectorAll('.btn-log-details').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var logId = this.dataset.logId;
        var modal = document.getElementById('logDetailsModal');
        document.getElementById('modalRequestData').textContent = 'Ładowanie...';
        document.getElementById('modalResponseData').textContent = '';
        document.getElementById('modalHeadersData').textContent = '';
        document.getElementById('modalUserAgent').textContent = '';
        new bootstrap.Modal(modal).show();

        fetch('{{ url("/publigo/webhooks/logs") }}/' + logId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('modalLogDate').textContent = data.created_at;
            var statusEl = document.getElementById('modalLogStatus');
            statusEl.className = 'badge ' + (data.success ? 'bg-success' : 'bg-danger');
            statusEl.textContent = data.success ? 'Sukces' : 'Błąd';
            document.getElementById('modalLogStatusCode').textContent = data.status_code ? 'HTTP ' + data.status_code : '';

            var errorEl = document.getElementById('modalLogError');
            if (data.error_message) {
                errorEl.textContent = data.error_message;
                errorEl.classList.remove('d-none');
            } else {
                errorEl.classList.add('d-none');
            }

            document.getElementById('modalRequestData').textContent = data.request_data != null
                ? JSON.stringify(data.request_data, null, 2) : '(brak danych)';
            document.getElementById('modalResponseData').textContent = data.response_data != null
                ? JSON.stringify(data.response_data, null, 2) : '(brak danych)';
            document.getElementById('modalHeadersData').textContent = data.headers != null
                ? JSON.stringify(data.headers, null, 2) : '(brak danych)';
            document.getElementById('modalUserAgent').textContent = data.user_agent || '(brak)';
        })
        .catch(function(err) {
            document.getElementById('modalRequestData').textContent = 'Błąd ładowania: ' + err.message;
        });
    });
});
</script>
</x-app-layout>
