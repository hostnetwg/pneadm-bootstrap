<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-box-seam"></i> Produkty z Publigo (WP IDEA)
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-card-list"></i> Lista produktów pobranych z API
                    </h5>
                </div>
                <div class="card-body">
                    @if($result['status'] === 'success')
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Pomyślnie pobrano <strong>{{ $result['count'] }}</strong> produktów z instancji: <code>{{ $baseUrl }}</code>
                        </div>

                        {{-- Lista produktów --}}
                        @if($result['count'] > 0 && is_array($result['body']))
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID Produktu</th>
                                            <th>Nazwa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($result['body'] as $productId => $productName)
                                            <tr>
                                                <td><code>{{ $productId }}</code></td>
                                                <td>{{ $productName }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif($result['count'] === 0)
                            <div class="alert alert-secondary mb-0">
                                <i class="bi bi-info-circle"></i> Połączenie udane, ale API nie zwróciło żadnych produktów.
                            </div>
                        @endif

                    @else
                        {{-- Komunikat o błędzie --}}
                        <div class="alert alert-danger">
                            <h6><i class="bi bi-x-circle"></i> Pobieranie produktów nieudane</h6>
                            <p class="mb-1"><strong>Status:</strong> {{ $result['status'] }}</p>
                            <p class="mb-0"><strong>Wiadomość:</strong> {{ $result['message'] }}</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
