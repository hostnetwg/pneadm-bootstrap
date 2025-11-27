<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Monitorowanie jakości szkoleń
            </h2>
            <form action="{{ route('dashboard.refresh') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm" title="Odśwież statystyki">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Odśwież
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(isset($error))
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ $error }}
                </div>
            @endif

            <!-- Metryki globalne -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">Średnia ocena</h6>
                                    <h2 class="mb-0">{{ number_format($averageRating, 2) }}</h2>
                                    <small class="text-white-50">ze wszystkich ankiet</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-star-fill fs-1 opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50 mb-2">NPS</h6>
                                    <h2 class="mb-0">{{ $npsData['nps'] }}</h2>
                                    <small class="text-white-50">
                                        ze wszystkich ankiet
                                    </small>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-graph-up-arrow fs-1 opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ostatnie szkolenia -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Ostatnie 10 szkoleń
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($recentSurveys->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Szkolenie</th>
                                                <th class="text-center">Data</th>
                                                <th class="text-center">Ocena</th>
                                                <th class="text-center">NPS</th>
                                                <th class="text-center">Odp.</th>
                                                <th class="text-center">Uczestnicy</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($recentSurveys as $item)
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong>
                                                            <a href="{{ route('surveys.show', $item['survey_id']) }}" class="text-decoration-none text-primary">
                                                                {{ $item['course_title'] }}
                                                                <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                                            </a>
                                                        </strong>
                                                        @if($item['instructor'])
                                                            <br><small class="text-muted">{{ $item['instructor'] }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    @if($item['course_date'])
                                                        <span class="badge bg-light text-dark">{{ $item['course_date']->format('d.m.Y') }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($item['rating'] > 0)
                                                        <span class="badge bg-success fs-6">{{ number_format($item['rating'], 2) }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-info">{{ $item['nps'] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary">{{ $item['responses_count'] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning text-dark">{{ $item['participants_count'] ?? 0 }}</span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted text-center mb-0">Brak danych</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trend czasowy -->
            @if($timeTrend->isNotEmpty() && $timeTrend->sum('surveys_count') > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="bi bi-graph-up me-2"></i>
                                Trend średniej oceny (ostatnie 6 miesięcy)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="timeTrendChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Rankingi -->
            <div class="row">
                <!-- Top 5 najlepiej ocenianych -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-trophy-fill me-2"></i>
                                Top 5 najlepiej ocenianych szkoleń
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($topSurveys->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Szkolenie</th>
                                                <th class="text-center">Ocena</th>
                                                <th class="text-center">NPS</th>
                                                <th class="text-center">Odp.</th>
                                                <th class="text-center">Uczestnicy</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($topSurveys as $index => $item)
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong>
                                                            <a href="{{ route('surveys.show', $item['survey_id']) }}" class="text-decoration-none text-primary">
                                                                {{ $item['course_title'] }}
                                                                <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                                            </a>
                                                        </strong>
                                                        @if($item['course_date'])
                                                            <br><small class="text-muted">{{ $item['course_date']->format('d.m.Y') }}</small>
                                                        @endif
                                                        @if($item['instructor'])
                                                            <br><small class="text-muted">{{ $item['instructor'] }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-success fs-6">{{ number_format($item['rating'], 2) }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-info">{{ $item['nps'] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary">{{ $item['responses_count'] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning text-dark">{{ $item['participants_count'] ?? 0 }}</span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted text-center mb-0">Brak danych</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Top 5 najgorzej ocenianych -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Top 5 najgorzej ocenianych szkoleń
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($bottomSurveys->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Szkolenie</th>
                                                <th class="text-center">Ocena</th>
                                                <th class="text-center">NPS</th>
                                                <th class="text-center">Odp.</th>
                                                <th class="text-center">Uczestnicy</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($bottomSurveys as $index => $item)
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong>
                                                            <a href="{{ route('surveys.show', $item['survey_id']) }}" class="text-decoration-none text-primary">
                                                                {{ $item['course_title'] }}
                                                                <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                                            </a>
                                                        </strong>
                                                        @if($item['course_date'])
                                                            <br><small class="text-muted">{{ $item['course_date']->format('d.m.Y') }}</small>
                                                        @endif
                                                        @if($item['instructor'])
                                                            <br><small class="text-muted">{{ $item['instructor'] }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning text-dark fs-6">{{ number_format($item['rating'], 2) }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-info">{{ $item['nps'] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary">{{ $item['responses_count'] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning text-dark">{{ $item['participants_count'] ?? 0 }}</span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted text-center mb-0">Brak danych</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informacja o aktualizacji -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="text-muted small mb-0">
                            @if(isset($isCached) && $isCached)
                                <i class="bi bi-info-circle me-1"></i>
                                Statystyki są buforowane (aktualizowane raz dziennie)
                            @endif
                        </p>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-clock me-1"></i>
                            Ostatnia aktualizacja: {{ isset($lastUpdated) ? $lastUpdated->format('d.m.Y H:i') : now()->format('d.m.Y H:i') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Trend czasowy
        @if($timeTrend->isNotEmpty() && $timeTrend->sum('surveys_count') > 0)
        const timeTrendCtx = document.getElementById('timeTrendChart');
        if (timeTrendCtx) {
            new Chart(timeTrendCtx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($timeTrend->pluck('month_name')) !!},
                    datasets: [{
                        label: 'Średnia ocena',
                        data: {!! json_encode($timeTrend->pluck('average_rating')) !!},
                        borderColor: 'rgba(13, 110, 253, 1)',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 0,
                            max: 5,
                            ticks: {
                                stepSize: 0.5
                            }
                        }
                    }
                }
            });
        }
        @endif
    </script>
    @endpush
</x-app-layout>
