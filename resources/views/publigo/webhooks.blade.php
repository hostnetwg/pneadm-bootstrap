<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-webhook me-2"></i>Zarządzanie Webhookami Publigo
        </h2>
    </x-slot>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1 text-dark">
                        <i class="bi bi-webhook text-primary me-2"></i>Webhooki Publigo
                    </h1>
                    <p class="text-muted mb-0">Konfiguracja i monitorowanie integracji z Publigo.pl</p>
                </div>
                <div>
                    <a href="{{ route('publigo.webhooks.logs') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list-ul me-1"></i>Wszystkie logi
                    </a>
                </div>
            </div>
            <div class="alert alert-info d-flex align-items-center mb-4">
                <i class="bi bi-info-circle me-2"></i>
                <div>
                    <strong>Uwaga:</strong> Webhook obsługuje tylko kursy z <code>source_id_old = "certgen_Publigo"</code>. 
                    Kursy bez tego ustawienia nie będą automatycznie zapisywać uczestników.
                </div>
            </div>
        </div>
    </div>

    <!-- Konfiguracja Webhooka -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="bi bi-gear me-2"></i>Konfiguracja Webhooka
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">URL Webhooka</label>
                    <div class="input-group">
                        <input type="text" 
                               value="{{ $webhookUrl }}" 
                               readonly 
                               class="form-control bg-light">
                        <button onclick="copyToClipboard('{{ $webhookUrl }}')" 
                                class="btn btn-primary" type="button">
                            <i class="bi bi-clipboard me-1"></i>Kopiuj
                        </button>
                    </div>
                    <div class="form-text">Skopiuj ten URL i wklej w panelu Publigo.pl</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Klucz API</label>
                    <div class="input-group">
                        <input type="password" 
                               value="{{ config('services.publigo.api_key') ? '***' . substr(config('services.publigo.api_key'), -4) : 'Nie skonfigurowano' }}" 
                               readonly 
                               class="form-control bg-light">
                        <button onclick="togglePasswordVisibility(this)" 
                                class="btn btn-outline-secondary" type="button">
                            <i class="bi bi-eye me-1"></i>Pokaż
                        </button>
                    </div>
                    <div class="form-text">Klucz licencyjny do weryfikacji podpisu HMAC-SHA256</div>
                    <small class="text-muted">Publigo używa nagłówka <code>x-wpidea-signature</code></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Webhooka -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="card-title mb-0">
                <i class="bi bi-play-circle me-2"></i>Test Webhooka
            </h5>
        </div>
        <div class="card-body">
            <form action="{{ route('publigo.test-webhook') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">ID Kursu</label>
                        <select name="course_id" required class="form-select">
                            <option value="">Wybierz kurs</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id_old ?? $course->id }}">
                                    {{ $course->title }} ({{ $course->start_date ? $course->start_date->format('Y-m-d') : 'Brak daty' }}) - ID: {{ $course->id_old ?? $course->id }}
                                </option>
                            @endforeach
                        </select>
                        @if($courses->count() === 0)
                            <div class="form-text text-danger">Brak kursów z source_id_old = "certgen_Publigo"</div>
                        @endif
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Email Testowy</label>
                        <input type="email" 
                               name="email" 
                               value="test@example.com" 
                               required 
                               class="form-control">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Imię</label>
                        <input type="text" 
                               name="first_name" 
                               value="Jan" 
                               required 
                               class="form-control">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Nazwisko</label>
                        <input type="text" 
                               name="last_name" 
                               value="Kowalski" 
                               required 
                               class="form-control">
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send me-1"></i>Wyślij Testowy Webhook
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ostatnie Logi -->
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Ostatnie Logi Webhooków
                </h5>
                <a href="{{ route('publigo.webhooks.logs') }}" 
                   class="btn btn-outline-light btn-sm">
                    <i class="bi bi-list-ul me-1"></i>Wszystkie logi
                </a>
            </div>
        </div>
        <div class="card-body">
            @if($recentLogs->count() > 0)
                <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                    @foreach($recentLogs as $log)
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex w-100 justify-content-between">
                                <small class="text-muted">{{ $log['timestamp'] }}</small>
                            </div>
                            <p class="mb-1 small">{{ $log['message'] }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4">
                    <i class="bi bi-inbox display-4 text-muted"></i>
                    <p class="text-muted mt-2">Brak logów webhooków</p>
                </div>
            @endif
        </div>
    </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Pokaż toast notification zamiast alert
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle me-2"></i>URL skopiowany do schowka!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Usuń toast po 3 sekundach
        setTimeout(() => {
            toast.remove();
        }, 3000);
    });
}

function togglePasswordVisibility(button) {
    const input = button.previousElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash me-1';
        button.innerHTML = '<i class="bi bi-eye-slash me-1"></i>Ukryj';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye me-1';
        button.innerHTML = '<i class="bi bi-eye me-1"></i>Pokaż';
    }
}
</script>
</x-app-layout>
