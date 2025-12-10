<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-calculator"></i> Test połączenia iFirma.pl
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
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>URL API:</strong>
                            <code class="d-block mt-1 p-2 bg-light">{{ $baseUrl }}</code>
                        </div>
                        <div class="col-md-6">
                            <strong>Login:</strong>
                            <code class="d-block mt-1 p-2 bg-light">{{ $login }}</code>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <strong>Klucze autoryzacji:</strong>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Typ klucza</th>
                                            <th>Klucz (maskowany)</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($maskedKeys as $keyName => $maskedKey)
                                            <tr>
                                                <td><strong>{{ ucfirst($keyName) }}</strong></td>
                                                <td><code>{{ $maskedKey }}</code></td>
                                                <td>
                                                    @if($maskedKey !== 'Brak')
                                                        <span class="badge bg-success">Skonfigurowany</span>
                                                    @else
                                                        <span class="badge bg-danger">Brak</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
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
                {{-- Test połączenia --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-wifi"></i> Status połączenia
                        </h5>
                    </div>
                    <div class="card-body">
                        @php $connection = $results['connection']; @endphp
                        
                        @if($connection['status'] === 'success')
                            <div class="alert alert-success">
                                <h6><i class="bi bi-check-circle"></i> Połączenie udane!</h6>
                                <p class="mb-1"><strong>Status HTTP:</strong> {{ $connection['status_code'] ?? 'N/A' }}</p>
                                @if(isset($connection['message']))
                                    <p class="mb-0">{{ $connection['message'] }}</p>
                                @endif
                                
                                @if(isset($connection['data']) && is_array($connection['data']))
                                    <hr class="my-2">
                                    <p class="mb-1"><strong>Odpowiedź API:</strong></p>
                                    <pre class="bg-light p-2 rounded mb-0" style="max-height: 300px; overflow-y: auto;"><code>{{ json_encode($connection['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                @endif
                            </div>
                        @elseif($connection['status'] === 'config_error')
                            <div class="alert alert-warning">
                                <h6><i class="bi bi-exclamation-triangle"></i> Błąd konfiguracji</h6>
                                <p class="mb-0"><strong>{{ $connection['message'] }}</strong></p>
                                <hr class="my-2">
                                <p class="mb-0 small">
                                    Sprawdź, czy w pliku <code>.env</code> są ustawione następujące zmienne:<br>
                                    - <code>IFIRMA_LOGIN</code><br>
                                    - <code>IFIRMA_KEY_FAKTURA</code> (wymagane do testu połączenia)
                                </p>
                            </div>
                        @elseif($connection['status'] === 'error')
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-x-circle"></i> Połączenie nieudane</h6>
                                <p class="mb-1"><strong>Status HTTP:</strong> {{ $connection['status_code'] ?? 'N/A' }}</p>
                                @if(isset($connection['message']))
                                    <p class="mb-1"><strong>Komunikat:</strong> {{ $connection['message'] }}</p>
                                @endif
                                
                                @if(isset($connection['details']))
                                    <details class="mt-2">
                                        <summary class="cursor-pointer">Pokaż szczegóły odpowiedzi</summary>
                                        <pre class="bg-light p-2 rounded mt-2 mb-0" style="max-height: 200px; overflow-y: auto;"><code>{{ $connection['details'] }}</code></pre>
                                    </details>
                                @endif
                                
                                <hr class="my-2">
                                <p class="mb-0 small">
                                    <strong>Możliwe przyczyny:</strong><br>
                                    1. Nieprawidłowy login lub klucz autoryzacji<br>
                                    2. Klucz autoryzacji wygasł lub został usunięty<br>
                                    3. Problem z dostępem do API iFirma.pl<br>
                                    4. Błędna konfiguracja URL API
                                </p>
                            </div>
                        @elseif($connection['status'] === 'exception')
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-x-circle"></i> Błąd podczas wykonywania żądania</h6>
                                <p class="mb-1"><strong>Typ błędu:</strong> {{ $connection['error_type'] ?? 'Exception' }}</p>
                                @if(isset($connection['message']))
                                    <p class="mb-0"><strong>Komunikat:</strong> {{ $connection['message'] }}</p>
                                @endif
                            </div>
                        @else
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle"></i> Status: {{ $connection['status'] ?? 'unknown' }}</h6>
                                @if(isset($connection['message']))
                                    <p class="mb-0">{{ $connection['message'] }}</p>
                                @endif
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
                        <a href="{{ route('ifirma.test-connection') }}" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Odśwież test
                        </a>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Powrót do dashboard
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

