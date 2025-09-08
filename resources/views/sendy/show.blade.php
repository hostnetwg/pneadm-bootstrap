<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły listy Sendy') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route('sendy.index') }}">Listy Sendy</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                {{ $list['name'] ?? 'Szczegóły listy' }}
                            </li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-list me-2"></i>
                        {{ $list['name'] ?? 'Szczegóły listy' }}
                    </h1>
                </div>
                <div class="btn-group" role="group">
                    <a href="{{ route('sendy.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Powrót do list
                    </a>
                    <button type="button" class="btn btn-outline-primary" id="refreshBtn" title="Odśwież dane">
                        <i class="fas fa-sync-alt"></i>
                        Odśwież
                    </button>
                </div>
            </div>

            @if(isset($error))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    {{ $error }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($list)
                <div class="row">
                    <!-- List Information -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Informacje o liście
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Nazwa listy:</label>
                                            <p class="form-control-plaintext">{{ $list['name'] ?? 'Brak nazwy' }}</p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Marka:</label>
                                            <p class="form-control-plaintext">{{ $list['brand_name'] ?? 'Nieznana' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">ID listy:</label>
                                            <p class="form-control-plaintext">
                                                <code>{{ $list['id'] ?? 'Brak ID' }}</code>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">ID marki:</label>
                                            <p class="form-control-plaintext">
                                                <code>{{ $list['brand_id'] ?? 'Brak ID' }}</code>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Statystyki
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="border-end">
                                            <h3 class="text-primary mb-1">{{ $list['active_subscribers'] ?? 0 }}</h3>
                                            <p class="text-muted mb-0">Aktywni subskrybenci</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border-end">
                                            <h3 class="text-info mb-1">-</h3>
                                            <p class="text-muted mb-0">Ostatnia kampania</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h3 class="text-warning mb-1">-</h3>
                                        <p class="text-muted mb-0">Wskaźnik otwarć</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tools me-2"></i>
                                    Akcje
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" 
                                            class="btn btn-success" 
                                            onclick="showSubscribeModal('{{ $list['id'] }}', '{{ $list['name'] ?? 'Lista' }}')">
                                        <i class="fas fa-plus me-2"></i>
                                        Dodaj subskrybenta
                                    </button>
                                    <button type="button" 
                                            class="btn btn-warning" 
                                            onclick="showUnsubscribeModal('{{ $list['id'] }}', '{{ $list['name'] ?? 'Lista' }}')">
                                        <i class="fas fa-minus me-2"></i>
                                        Usuń subskrybenta
                                    </button>
                                    <button type="button" 
                                            class="btn btn-info" 
                                            onclick="showStatusCheckModal('{{ $list['id'] }}', '{{ $list['name'] ?? 'Lista' }}')">
                                        <i class="fas fa-search me-2"></i>
                                        Sprawdź status
                                    </button>
                                    <hr>
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="copyListId('{{ $list['id'] }}')">
                                        <i class="fas fa-copy me-2"></i>
                                        Kopiuj ID listy
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Ostatnia aktualizacja
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-0">
                                    <i class="fas fa-calendar me-2"></i>
                                    {{ now()->format('d.m.Y H:i:s') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h4 class="text-muted">Lista nie została znaleziona</h4>
                    <p class="text-muted">Nie można wyświetlić szczegółów tej listy.</p>
                    <a href="{{ route('sendy.index') }}" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Powrót do list
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Subscribe Modal -->
<div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subscribeModalLabel">
                    <i class="fas fa-plus me-2"></i>
                    Dodaj subskrybenta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
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
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unsubscribeModalLabel">
                    <i class="fas fa-minus me-2"></i>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-minus me-2"></i>
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
                <div class="mb-3">
                    <label for="statusEmail" class="form-label">Adres email *</label>
                    <input type="email" class="form-control" id="statusEmail" required>
                </div>
                <div id="statusResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                <button type="button" class="btn btn-primary" onclick="checkStatus()">
                    <i class="fas fa-search me-2"></i>
                    Sprawdź status
                </button>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh button functionality
    document.getElementById('refreshBtn').addEventListener('click', function() {
        location.reload();
    });

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

function showStatusCheckModal(listId, listName) {
    document.getElementById('statusModalLabel').innerHTML = '<i class="fas fa-info-circle me-2"></i>Status subskrypcji - ' + listName;
    document.getElementById('statusResult').innerHTML = '';
    document.getElementById('statusEmail').value = '';
    new bootstrap.Modal(document.getElementById('statusModal')).show();
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
            setTimeout(() => location.reload(), 1000);
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
            setTimeout(() => location.reload(), 1000);
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

function checkStatus() {
    const email = document.getElementById('statusEmail').value;
    const listId = '{{ $list["id"] ?? "" }}';
    
    if (!email) {
        showAlert('Proszę podać adres email', 'warning');
        return;
    }
    
    showLoading();
    
    fetch('{{ route("sendy.check-status") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            email: email,
            list_id: listId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            const statusColors = {
                'Subscribed': 'success',
                'Unsubscribed': 'warning',
                'Unconfirmed': 'info',
                'Bounced': 'danger',
                'Soft bounced': 'warning',
                'Complained': 'danger'
            };
            
            const color = statusColors[data.status] || 'secondary';
            const icon = data.status === 'Subscribed' ? 'check-circle' : 
                        data.status === 'Unsubscribed' ? 'times-circle' : 'question-circle';
            
            document.getElementById('statusResult').innerHTML = `
                <div class="alert alert-${color}">
                    <i class="fas fa-${icon} me-2"></i>
                    <strong>Status:</strong> ${data.status}
                    <br>
                    <strong>Email:</strong> ${data.email}
                </div>
            `;
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Wystąpił błąd podczas sprawdzania statusu', 'danger');
        console.error('Error:', error);
    });
}

function copyListId(listId) {
    navigator.clipboard.writeText(listId).then(function() {
        showAlert('ID listy zostało skopiowane do schowka', 'success');
    }, function(err) {
        showAlert('Nie udało się skopiować ID listy', 'danger');
        console.error('Could not copy text: ', err);
    });
}

function showLoading() {
    // Simple loading indicator
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Przetwarzanie...';
    });
}

function hideLoading() {
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = btn.getAttribute('data-original-text') || btn.innerHTML;
    });
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
