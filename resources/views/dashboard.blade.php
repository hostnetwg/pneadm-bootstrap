<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(isset($error))
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ $error }}
                </div>
            @endif

            <!-- Statystyki dzisiejsze -->
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="text-primary mb-3">
                        <i class="bi bi-calendar-day me-2"></i>
                        Dzisiejsze zamówienia
                    </h4>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Zamówienia Publigo</h6>
                                    <h3 class="mb-0">{{ $todayOrdersCount }}</h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-cart-check fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Formularze zamówień</h6>
                                    <h3 class="mb-0">{{ $todayFormsCount }}</h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-file-text fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Wartość dzisiaj</h6>
                                    <h3 class="mb-0">{{ number_format($todayOrdersValue, 0, ',', ' ') }} zł</h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-currency-dollar fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Oczekujące</h6>
                                    <h3 class="mb-0">{{ $pendingForms }}</h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-clock fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statystyki miesięczne -->
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="text-primary mb-3">
                        <i class="bi bi-calendar-month me-2"></i>
                        Bieżący miesiąc
                    </h4>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Zamówienia Publigo</h6>
                                    <h3 class="mb-0">{{ $monthlyOrdersCount }}</h3>
                                    @if($ordersTrend != 0)
                                        <small class="text-{{ $ordersTrend > 0 ? 'success' : 'danger' }}">
                                            <i class="bi bi-arrow-{{ $ordersTrend > 0 ? 'up' : 'down' }}"></i>
                                            {{ abs($ordersTrend) }}%
                                        </small>
                                    @endif
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-cart-check fs-1 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Formularze zamówień</h6>
                                    <h3 class="mb-0">{{ $monthlyFormsCount }}</h3>
                                    @if($formsTrend != 0)
                                        <small class="text-{{ $formsTrend > 0 ? 'success' : 'danger' }}">
                                            <i class="bi bi-arrow-{{ $formsTrend > 0 ? 'up' : 'down' }}"></i>
                                            {{ abs($formsTrend) }}%
                                        </small>
                                    @endif
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-file-text fs-1 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Wartość miesięczna</h6>
                                    <h3 class="mb-0">{{ number_format($monthlyOrdersValue, 0, ',', ' ') }} zł</h3>
                                    @if($valueTrend != 0)
                                        <small class="text-{{ $valueTrend > 0 ? 'success' : 'danger' }}">
                                            <i class="bi bi-arrow-{{ $valueTrend > 0 ? 'up' : 'down' }}"></i>
                                            {{ abs($valueTrend) }}%
                                        </small>
                                    @endif
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-currency-dollar fs-1 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Płatności online</h6>
                                    <h3 class="mb-0">{{ $onlinePayments->count() }}</h3>
                                    <small class="text-muted">
                                        {{ $invoicePayments->count() }} faktury
                                    </small>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-credit-card fs-1 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Najpopularniejsze produkty -->
            @if($popularProducts->count() > 0)
            <div class="row">
                <div class="col-12">
                    <h4 class="text-primary mb-3">
                        <i class="bi bi-trophy me-2"></i>
                        Najpopularniejsze produkty ({{ now()->format('F Y') }})
                    </h4>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Produkt</th>
                                            <th class="text-center">Liczba zamówień</th>
                                            <th class="text-end">Wartość</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($popularProducts as $product)
                                        <tr>
                                            <td>
                                                <strong>{{ $product['name'] }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary">{{ $product['count'] }}</span>
                                            </td>
                                            <td class="text-end">
                                                <strong>{{ number_format($product['value'], 0, ',', ' ') }} zł</strong>
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
            @endif

            <!-- Szybkie linki -->
            <div class="row mt-4">
                <div class="col-12">
                    <h4 class="text-primary mb-3">
                        <i class="bi bi-link-45deg me-2"></i>
                        Szybkie linki
                    </h4>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="{{ route('certgen.zamowienia.index') }}" class="btn btn-outline-primary w-100">
                        <i class="bi bi-cart-check me-2"></i>
                        Wszystkie zamówienia
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="{{ route('sales.index') }}" class="btn btn-outline-info w-100">
                        <i class="bi bi-file-text me-2"></i>
                        Formularze zamówień
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="{{ route('sales.index', ['filter' => 'new']) }}" class="btn btn-outline-warning w-100">
                        <i class="bi bi-clock me-2"></i>
                        Oczekujące zamówienia
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="{{ route('courses.index') }}" class="btn btn-outline-success w-100">
                        <i class="bi bi-book me-2"></i>
                        Szkolenia
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
