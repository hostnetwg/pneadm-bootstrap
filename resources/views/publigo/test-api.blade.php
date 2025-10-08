<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-plug"></i> Test API Publigo.pl (WP IDEA)
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            
            {{-- Informacje o konfiguracji --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear"></i> Konfiguracja API
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>URL instancji:</strong>
                            <code class="d-block mt-1 p-2 bg-light">{{ $baseUrl }}</code>
                        </div>
                        <div class="col-md-6">
                            <strong>Klucz API (v1):</strong>
                            <code class="d-block mt-1 p-2 bg-light">{{ $apiKey }}</code>
                        </div>
                    </div>
                </div>
            </div>

            @if(isset($results['error']))
                {{-- Globalny błąd --}}
                <div class="alert alert-danger">
                    <h5><i class="bi bi-exclamation-triangle"></i> Wystąpił błąd krytyczny</h5>
                    <p class="mb-0"><strong>Błąd:</strong> {{ $results['error'] }}</p>
                </div>
            @else
                {{-- Test 1: Połączenie z API --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-wifi"></i> Wynik testu: Połączenie i pobranie produktów
                        </h5>
                    </div>
                    <div class="card-body">
                        @php $response = $results['connection']; @endphp
                        
                        @if($response['status'] === 'success')
                            <div class="alert alert-success">
                                <h6><i class="bi bi-check-circle"></i> Połączenie udane!</h6>
                                <p class="mb-1"><strong>Status HTTP:</strong> {{ $response['status_code'] }}</p>
                                <p class="mb-1"><strong>Endpoint:</strong> <code>/wp-json/wp-idea/v1/products</code></p>
                                <p class="mb-0"><strong>Znaleziono produktów:</strong> <span class="badge bg-primary">{{ $response['count'] }}</span></p>
                            </div>

                            {{-- Lista produktów --}}
                            @if($response['count'] > 0 && is_array($response['body']))
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID Produktu</th>
                                                <th>Nazwa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($response['body'] as $productId => $productName)
                                                <tr>
                                                    <td><code>{{ $productId }}</code></td>
                                                    <td>{{ $productName }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @elseif($response['count'] === 0)
                                <div class="alert alert-secondary mb-0">
                                    <i class="bi bi-info-circle"></i> Połączenie udane, ale API nie zwróciło żadnych produktów.
                                </div>
                            @endif

                        @elseif($response['status'] === 'config_error')
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-shield-exclamation"></i> Błąd Konfiguracji</h6>
                                <p class="mb-0">{{ $response['message'] }}</p>
                            </div>
                        @else
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-x-circle"></i> Połączenie nieudane</h6>
                                <p class="mb-1"><strong>Status:</strong> {{ $response['status'] }}</p>
                                @if(isset($response['status_code']))
                                    <p class="mb-1"><strong>Kod HTTP:</strong> {{ $response['status_code'] }}</p>
                                @endif
                                <p class="mb-1"><strong>Wiadomość:</strong> {{ $response['message'] }}</p>
                                <details>
                                    <summary>Pokaż szczegóły techniczne</summary>
                                    <p class="mb-1 mt-2"><strong>Wysłany nonce:</strong> <code>{{ $response['nonce_sent'] ?? 'N/A' }}</code></p>
                                    <p class="mb-1"><strong>Wysłany token:</strong> <code>{{ $response['token_sent'] ?? 'N/A' }}</code></p>
                                    @if(isset($response['body']))
                                        <p class="mb-1"><strong>Odpowiedź serwera:</strong></p>
                                        <pre class="bg-light p-2 rounded"><code>{{ is_string($response['body']) ? $response['body'] : json_encode($response['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                    @endif
                                </details>
                                <hr>
                                <p class="mb-0 small"><strong>Wskazówki:</strong><br>
                                    1. Sprawdź, czy Klucz API (`{{ $apiKey }}`) jest poprawny i aktywny w panelu WP Idea.<br>
                                    2. Upewnij się, że wtyczki bezpieczeństwa w WordPress nie blokują zapytań do REST API.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Przyciski akcji --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-tools"></i> Akcje
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2">
                        <a href="{{ route('publigo.test-api') }}" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Odśwież test
                        </a>
                        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Powrót do zamówień
                        </a>
                        <a href="{{ route('publigo.webhooks') }}" class="btn btn-outline-info">
                            <i class="bi bi-link-45deg"></i> Zarządzanie webhookami
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
