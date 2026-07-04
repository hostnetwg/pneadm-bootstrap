<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            Dashboard zamówień
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <div class="row mb-4 g-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-primary h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-1">Dziś (FORM)</h6>
                                    <h2 class="mb-0 text-primary">{{ number_format($stats['form_today']) }}</h2>
                                    <small class="text-muted">wczoraj: {{ number_format($stats['form_yesterday']) }}</small>
                                </div>
                                <i class="bi bi-calendar-check fs-2 text-primary opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-warning h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-1">Do obsługi</h6>
                                    <h2 class="mb-0 text-warning">{{ number_format($stats['form_handling']) }}</h2>
                                    <small class="text-muted">aktywne szkolenia</small>
                                </div>
                                <i class="bi bi-inbox fs-2 text-warning opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-success h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-1">Wartość z FV</h6>
                                    <h2 class="mb-0 text-success">{{ number_format($stats['form_invoiced_value'], 0, ',', ' ') }} zł</h2>
                                    <small class="text-muted">łącznie {{ number_format($stats['form_total']) }} zamówień</small>
                                </div>
                                <i class="bi bi-cash-stack fs-2 text-success opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-info h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-1">Płatności online</h6>
                                    <h2 class="mb-0 text-info">{{ number_format($stats['online_pending']) }}</h2>
                                    <small class="text-muted">oczekujące · opłacone dziś: {{ number_format($stats['online_paid_today']) }}</small>
                                </div>
                                <i class="bi bi-credit-card fs-2 text-info opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        @if(($chartGranularity ?? 'day') === 'month')
                            Zamówienia wg miesiąca
                        @else
                            Zamówienia wg dnia
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    @if($dateRangeError)
                        <div class="alert alert-warning py-2 small mb-3" role="alert">
                            {{ $dateRangeError }} Przywrócono domyślny zakres.
                        </div>
                    @endif

                    @if(!empty($datePresets))
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="small text-muted me-1 align-self-center">Krótki zakres:</span>
                            @foreach($datePresets as $preset)
                                @php
                                    $isActive = ($filters['date_from'] ?? null) === $preset['date_from']
                                        && ($filters['date_to'] ?? null) === $preset['date_to'];
                                @endphp
                                <a href="{{ route('dashboard', ['date_from' => $preset['date_from'], 'date_to' => $preset['date_to']]) }}"
                                   class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    {{ $preset['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($datePresetsYears))
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            <span class="small text-muted me-1 align-self-center">Lata:</span>
                            @foreach($datePresetsYears as $preset)
                                @php
                                    $isActive = ($filters['date_from'] ?? null) === $preset['date_from']
                                        && ($filters['date_to'] ?? null) === $preset['date_to'];
                                @endphp
                                <a href="{{ route('dashboard', ['date_from' => $preset['date_from'], 'date_to' => $preset['date_to']]) }}"
                                   class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    {{ $preset['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <form method="GET" action="{{ route('dashboard') }}" class="row g-2 align-items-end mb-4">
                        <div class="col-sm-6 col-md-3 col-lg-2">
                            <label for="date_from" class="form-label small mb-1">Od</label>
                            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" min="2020-01-01">
                        </div>
                        <div class="col-sm-6 col-md-3 col-lg-2">
                            <label for="date_to" class="form-label small mb-1">Do</label>
                            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filtruj
                            </button>
                        </div>
                        <div class="col-auto">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Domyślny zakres</a>
                        </div>
                    </form>

                    <div class="d-flex flex-wrap gap-3 mb-3 small text-muted">
                        <span>
                            Okres:
                            <strong>{{ $filters['date_from'] }}</strong>
                            →
                            <strong>{{ $filters['date_to'] }}</strong>
                            ({{ $tz }})
                        </span>
                        <span>Łącznie: <strong>{{ number_format($stats['period_total']) }}</strong></span>
                        <span>Online: <strong>{{ number_format($stats['period_online']) }}</strong></span>
                        <span>Odroczone: <strong>{{ number_format($stats['period_deferred']) }}</strong></span>
                        <span>Średnio/{{ $stats['period_avg_label'] ?? 'dzień' }}: <strong>{{ number_format($stats['period_avg'], 1, ',', ' ') }}</strong></span>
                        @if(($chartGranularity ?? 'day') === 'month')
                            <span class="text-muted">(wykres: agregacja miesięczna — zakres &gt; 90 dni)</span>
                        @endif
                    </div>
                    <p class="small text-muted mb-3 mb-md-2">
                        Z numerem faktury lub w kolejce do obsługi (bez pełnego zamknięcia) · data złożenia zamówienia ·
                        odroczone = faktura z odroczonym terminem (w tym starsze bez trybu płatności)
                    </p>

                    <div style="position: relative; height: 320px;">
                        <canvas id="ordersDailyChart" aria-label="Wykres liczby zamówień wg dnia"></canvas>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Ostatnie zamówienia FORM</h5>
                            <a href="{{ route('form-orders.index') }}" class="btn btn-sm btn-outline-primary">
                                Pełna lista
                            </a>
                        </div>
                        <div class="card-body p-0">
                            @if($recentFormOrders->isEmpty())
                                <p class="text-muted p-3 mb-0">Brak zamówień do wyświetlenia.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Ident</th>
                                                <th>Szkolenie</th>
                                                <th>Data</th>
                                                <th class="text-end">Kwota</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($recentFormOrders as $order)
                                                <tr>
                                                    <td><code>{{ $order->ident }}</code></td>
                                                    <td class="text-truncate" style="max-width: 220px;" title="{{ $order->course?->title ?? '—' }}">
                                                        {{ $order->course?->title ?? '—' }}
                                                    </td>
                                                    <td>{{ $order->formatOrderDateLocal('d.m.Y H:i') ?? '—' }}</td>
                                                    <td class="text-end">
                                                        @if($order->product_price)
                                                            {{ number_format((float) $order->product_price, 2, ',', ' ') }} zł
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="{{ route('form-orders.show', $order->id) }}" class="btn btn-sm btn-link">Szczegóły</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Szybkie skróty</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="{{ route('form-orders.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-text me-2"></i>Zamówienia FORM</span>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                            <a href="{{ route('form-orders.index', ['quick' => 'handling']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-inbox me-2"></i>Kolejka do obsługi</span>
                                <span class="badge text-bg-warning rounded-pill">{{ $stats['form_handling'] }}</span>
                            </a>
                            <a href="{{ route('online-payment-orders.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-credit-card me-2"></i>Płatności online</span>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                            @if(config('analytics.revenue_dashboard.enabled', true))
                                <a href="{{ route('analytics.revenue.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-graph-up me-2"></i>Analityka rozliczeń</span>
                                    <i class="bi bi-chevron-right text-muted"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const canvas = document.getElementById('ordersDailyChart');
            if (!canvas) {
                return;
            }

            const labels = @json($dailyChart['labels_short'] ?? []);
            const seriesOnline = @json($dailyChart['online'] ?? []);
            const seriesDeferred = @json($dailyChart['deferred'] ?? []);
            const seriesTotal = @json($dailyChart['total'] ?? []);
            const pointRadius = labels.length > 31 ? 2 : 4;

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Suma',
                            data: seriesTotal,
                            borderColor: 'rgba(13, 110, 253, 1)',
                            backgroundColor: 'rgba(13, 110, 253, 0.15)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.3,
                            pointRadius: pointRadius,
                            pointHoverRadius: 6,
                        },
                        {
                            label: 'Płatność online (bramka)',
                            data: seriesOnline,
                            borderColor: 'rgba(220, 53, 69, 1)',
                            backgroundColor: 'rgba(220, 53, 69, 0.08)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.3,
                            pointRadius: pointRadius,
                            pointHoverRadius: 6,
                        },
                        {
                            label: 'Faktura odroczona',
                            data: seriesDeferred,
                            borderColor: 'rgba(25, 135, 84, 1)',
                            backgroundColor: 'rgba(25, 135, 84, 0.08)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.3,
                            pointRadius: pointRadius,
                            pointHoverRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                title: function (items) {
                                    const idx = items[0]?.dataIndex ?? 0;
                                    const fullLabels = @json($dailyChart['labels'] ?? []);

                                    return fullLabels[idx] ?? items[0]?.label ?? '';
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: labels.length > 31 ? 24 : 31,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                },
            });
        })();
    </script>
    @endpush
</x-app-layout>
