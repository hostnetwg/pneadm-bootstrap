<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Listy Mailingowe Sendy') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-1 text-dark fw-bold">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                Listy Mailingowe Sendy
                            </h1>
                            <p class="text-muted mb-0">Zarządzaj listami mailingowymi z poziomu panelu administracyjnego</p>
                        </div>
                        <div class="btn-group shadow-sm" role="group">
                            <button type="button" class="btn btn-outline-primary" id="refreshBtn" title="Odśwież dane">
                                <i class="fas fa-sync-alt me-1"></i>
                                Odśwież
                            </button>
                            <button type="button" class="btn btn-outline-success" id="testConnectionBtn" title="Test połączenia">
                                <i class="fas fa-plug me-1"></i>
                                Test API
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if(isset($error))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    {{ $error }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Loading indicator -->
            <div id="loadingIndicator" class="text-center py-4" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Ładowanie...</span>
                </div>
                <p class="mt-2 text-muted">Pobieranie danych z Sendy API...</p>
            </div>

            <!-- Lists container -->
            <div id="listsContainer">
                @if(count($lists) > 0)
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0">{{ count($lists) }}</h4>
                                            <p class="mb-0 small">Listy mailingowe</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-list fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0">{{ array_sum(array_column($lists, 'active_subscribers')) }}</h4>
                                            <p class="mb-0 small">Aktywni subskrybenci</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-users fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0">{{ count(array_unique(array_column($lists, 'brand_name'))) }}</h4>
                                            <p class="mb-0 small">Marki</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-tags fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0">{{ now()->format('H:i') }}</h4>
                                            <p class="mb-0 small">Ostatnia aktualizacja</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lists Table -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="card-title mb-0 fw-bold text-dark">
                                <i class="fas fa-list me-2 text-primary"></i>
                                Listy Mailingowe
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 py-3 px-4">
                                                <i class="fas fa-list me-2 text-muted"></i>
                                                Nazwa listy
                                            </th>
                                            <th class="border-0 py-3 px-4">
                                                <i class="fas fa-tag me-2 text-muted"></i>
                                                Marka
                                            </th>
                                            <th class="border-0 py-3 px-4 text-center">
                                                <i class="fas fa-users me-2 text-muted"></i>
                                                Subskrybenci
                                            </th>
                                            <th class="border-0 py-3 px-4">
                                                <i class="fas fa-key me-2 text-muted"></i>
                                                ID listy
                                            </th>
                                            <th class="border-0 py-3 px-4 text-center">
                                                <i class="fas fa-cog me-2 text-muted"></i>
                                                Akcje
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($lists as $list)
                                            <tr class="border-bottom">
                                                <td class="py-3 px-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary rounded-circle p-2 me-3">
                                                            <i class="fas fa-envelope text-white"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-semibold text-dark">
                                                                {{ $list['name'] ?? 'Bez nazwy' }}
                                                            </h6>
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Ostatnia aktualizacja: {{ now()->format('d.m.Y H:i') }}
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="badge bg-light text-dark border">
                                                        <i class="fas fa-tag me-1"></i>
                                                        {{ $list['brand_name'] ?? 'Nieznana' }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-center">
                                                    <div class="d-flex align-items-center justify-content-center">
                                                        <span class="badge bg-success text-white fs-6 px-3 py-2">
                                                            <i class="fas fa-users me-1"></i>
                                                            {{ number_format($list['active_subscribers'] ?? 0) }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <code class="bg-light p-2 rounded text-break" style="font-size: 0.8rem;">
                                                        {{ $list['id'] ?? 'Brak ID' }}
                                                    </code>
                                                </td>
                                                <td class="py-3 px-4 text-center">
                                                    <div class="btn-group" role="group">
                                                        <a href="{{ route('sendy.show', $list['id']) }}" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           title="Zobacz szczegóły">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-outline-success btn-sm" 
                                                                onclick="showSubscribeModal('{{ $list['id'] }}', '{{ $list['name'] ?? 'Lista' }}')"
                                                                title="Dodaj subskrybenta">
                                                            <i class="fas fa-user-plus"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-outline-warning btn-sm" 
                                                                onclick="showUnsubscribeModal('{{ $list['id'] }}', '{{ $list['name'] ?? 'Lista' }}')"
                                                                title="Usuń subskrybenta">
                                                            <i class="fas fa-user-minus"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Empty State -->
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <div class="mb-4">
                                        <i class="fas fa-inbox fa-4x text-muted opacity-50"></i>
                                    </div>
                                    <h4 class="text-muted mb-3">Brak list mailingowych</h4>
                                    <p class="text-muted mb-4">Nie znaleziono żadnych list mailingowych w systemie Sendy. Sprawdź połączenie z API lub skontaktuj się z administratorem.</p>
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button type="button" class="btn btn-primary" id="refreshBtnEmpty">
                                            <i class="fas fa-sync-alt me-2"></i>
                                            Odśwież dane
                                        </button>
                                        <button type="button" class="btn btn-outline-info" id="testConnectionBtnEmpty">
                                            <i class="fas fa-plug me-2"></i>
                                            Test połączenia
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Subscribe Modal -->
<div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="subscribeModalLabel">
                    <i class="fas fa-user-plus me-2"></i>
                    Dodaj subskrybenta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="subscribeForm">
                <div class="modal-body">
                    <input type="hidden" id="subscribeListId" name="list_id">
                    <div class="mb-3">
                        <label for="subscribeEmail" class="form-label">Adres email *</label>
                        <input type="email" class="form-control" id="subscribeEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="subscribeName" class="form-label">Imię i nazwisko</label>
                        <input type="text" class="form-control" id="subscribeName" name="name">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="subscribeCountry" class="form-label">Kraj (kod 2-literowy)</label>
                                <input type="text" class="form-control" id="subscribeCountry" name="country" maxlength="2">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="subscribeIp" class="form-label">Adres IP</label>
                                <input type="text" class="form-control" id="subscribeIp" name="ipaddress">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="subscribeReferrer" class="form-label">Źródło (URL)</label>
                        <input type="url" class="form-control" id="subscribeReferrer" name="referrer">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="subscribeGdpr" name="gdpr" value="1">
                        <label class="form-check-label" for="subscribeGdpr">
                            Zgodność z GDPR
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="subscribeSilent" name="silent" value="1">
                        <label class="form-check-label" for="subscribeSilent">
                            Pomijaj podwójną opt-in
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Anuluj
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i>
                        Dodaj subskrybenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unsubscribe Modal -->
<div class="modal fade" id="unsubscribeModal" tabindex="-1" aria-labelledby="unsubscribeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold" id="unsubscribeModalLabel">
                    <i class="fas fa-user-minus me-2"></i>
                    Usuń subskrybenta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="unsubscribeForm">
                <div class="modal-body">
                    <input type="hidden" id="unsubscribeListId" name="list_id">
                    <div class="mb-3">
                        <label for="unsubscribeEmail" class="form-label">Adres email *</label>
                        <input type="email" class="form-control" id="unsubscribeEmail" name="email" required>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Uwaga:</strong> Ta operacja usunie subskrybenta z listy. Czy na pewno chcesz kontynuować?
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Anuluj
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-user-minus me-1"></i>
                        Usuń subskrybenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Check Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">
                    <i class="fas fa-info-circle me-2"></i>
                    Status subskrypcji
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="statusResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</x-app-layout>

@push('styles')
<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.btn {
    transition: all 0.2s ease-in-out;
}

.btn:hover {
    transform: translateY(-1px);
}

.badge {
    font-size: 0.75rem;
    padding: 0.5rem 0.75rem;
}

.opacity-75 {
    opacity: 0.75;
}

.opacity-50 {
    opacity: 0.5;
}

.shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.shadow-lg {
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175) !important;
}

.border-0 {
    border: 0 !important;
}

.gap-2 {
    gap: 0.5rem !important;
}

.text-break {
    word-break: break-all !important;
}

/* Table specific styles */
.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transition: background-color 0.2s ease-in-out;
}

.table th {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
}

.table td {
    vertical-align: middle;
    border-top: 1px solid #f1f3f4;
}

.rounded-circle {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-group .btn {
    border-radius: 0.375rem;
    margin: 0 2px;
}

.btn-group .btn:first-child {
    margin-left: 0;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
    color: #e83e8c;
}

.badge.fs-6 {
    font-size: 0.875rem !important;
    padding: 0.5rem 0.75rem;
}

.table-responsive {
    border-radius: 0.5rem;
    overflow: hidden;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh button functionality
    document.getElementById('refreshBtn').addEventListener('click', refreshLists);
    const refreshBtnEmpty = document.getElementById('refreshBtnEmpty');
    if (refreshBtnEmpty) {
        refreshBtnEmpty.addEventListener('click', refreshLists);
    }

    // Test connection button
    document.getElementById('testConnectionBtn').addEventListener('click', testConnection);
    const testConnectionBtnEmpty = document.getElementById('testConnectionBtnEmpty');
    if (testConnectionBtnEmpty) {
        testConnectionBtnEmpty.addEventListener('click', testConnection);
    }

    // Subscribe form
    document.getElementById('subscribeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        subscribeUser();
    });

    // Unsubscribe form
    document.getElementById('unsubscribeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        unsubscribeUser();
    });
});

function refreshLists() {
    showLoading();
    
    fetch('{{ route("sendy.refresh") }}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            location.reload();
        } else {
            showAlert('Błąd podczas odświeżania danych: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Wystąpił błąd podczas odświeżania danych', 'danger');
        console.error('Error:', error);
    });
}

function testConnection() {
    showLoading();
    
    fetch('{{ route("sendy.test-connection") }}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('Połączenie z Sendy API działa poprawnie! Znaleziono ' + data.brands_count + ' marek.', 'success');
        } else {
            showAlert('Błąd połączenia z Sendy API: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Wystąpił błąd podczas testowania połączenia', 'danger');
        console.error('Error:', error);
    });
}

function showSubscribeModal(listId, listName) {
    document.getElementById('subscribeListId').value = listId;
    document.getElementById('subscribeModalLabel').innerHTML = '<i class="fas fa-plus me-2"></i>Dodaj subskrybenta - ' + listName;
    new bootstrap.Modal(document.getElementById('subscribeModal')).show();
}

function showUnsubscribeModal(listId, listName) {
    document.getElementById('unsubscribeListId').value = listId;
    document.getElementById('unsubscribeModalLabel').innerHTML = '<i class="fas fa-minus me-2"></i>Usuń subskrybenta - ' + listName;
    new bootstrap.Modal(document.getElementById('unsubscribeModal')).show();
}

function subscribeUser() {
    const form = document.getElementById('subscribeForm');
    const formData = new FormData(form);
    
    showLoading();
    
    fetch('{{ route("sendy.subscribe") }}', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('subscribeModal')).hide();
            form.reset();
            setTimeout(() => refreshLists(), 1000);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Wystąpił błąd podczas dodawania subskrypcji', 'danger');
        console.error('Error:', error);
    });
}

function unsubscribeUser() {
    const form = document.getElementById('unsubscribeForm');
    const formData = new FormData(form);
    
    showLoading();
    
    fetch('{{ route("sendy.unsubscribe") }}', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('unsubscribeModal')).hide();
            form.reset();
            setTimeout(() => refreshLists(), 1000);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Wystąpił błąd podczas usuwania subskrypcji', 'danger');
        console.error('Error:', error);
    });
}

function showLoading() {
    document.getElementById('loadingIndicator').style.display = 'block';
    document.getElementById('listsContainer').style.opacity = '0.5';
}

function hideLoading() {
    document.getElementById('loadingIndicator').style.display = 'none';
    document.getElementById('listsContainer').style.opacity = '1';
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>
@endpush
