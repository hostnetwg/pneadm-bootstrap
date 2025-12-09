<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            Raporty księgowe
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @php
                $months = [
                    1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
                    5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
                    9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień'
                ];
            @endphp

            {{-- Komunikaty --}}
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Formularz filtrowania --}}
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtrowanie danych</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('accounting.reports.index') }}" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="filter_type" id="filter_year" value="year" 
                                           {{ $filterType === 'year' ? 'checked' : '' }} onchange="toggleFilterType()">
                                    <label class="btn btn-outline-primary" for="filter_year">
                                        <i class="bi bi-calendar-year me-1"></i>Rok
                                    </label>

                                    <input type="radio" class="btn-check" name="filter_type" id="filter_range" value="range" 
                                           {{ $filterType === 'range' ? 'checked' : '' }} onchange="toggleFilterType()">
                                    <label class="btn btn-outline-primary" for="filter_range">
                                        <i class="bi bi-calendar-range me-1"></i>Zakres dat
                                    </label>
                                </div>
                            </div>

                            {{-- Filtr: Rok --}}
                            <div id="yearFilter" class="col-md-12" style="display: {{ $filterType === 'year' ? 'block' : 'none' }};">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label for="year" class="form-label">Wybierz rok</label>
                                        <select class="form-select" id="year" name="year">
                                            @foreach($availableYears as $year)
                                                <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                                    {{ $year }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Filtr: Zakres dat --}}
                            <div id="rangeFilter" class="col-md-12" style="display: {{ $filterType === 'range' ? 'block' : 'none' }};">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="start_year" class="form-label">Rok (od)</label>
                                        <select class="form-select" id="start_year" name="start_year">
                                            @foreach($availableYears as $year)
                                                <option value="{{ $year }}" {{ $startYear == $year ? 'selected' : '' }}>
                                                    {{ $year }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="start_month" class="form-label">Miesiąc (od)</label>
                                        <select class="form-select" id="start_month" name="start_month">
                                            @foreach($months as $num => $name)
                                                <option value="{{ $num }}" {{ $startMonth == $num ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="end_year" class="form-label">Rok (do)</label>
                                        <select class="form-select" id="end_year" name="end_year">
                                            @foreach($availableYears as $year)
                                                <option value="{{ $year }}" {{ $endYear == $year ? 'selected' : '' }}>
                                                    {{ $year }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="end_month" class="form-label">Miesiąc (do)</label>
                                        <select class="form-select" id="end_month" name="end_month">
                                            @foreach($months as $num => $name)
                                                <option value="{{ $num }}" {{ $endMonth == $num ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-2"></i>Filtruj
                                </button>
                                <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Resetuj
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Wykres porównawczy miesiąc do miesiąca dla wszystkich lat --}}
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart me-2"></i>Porównanie miesiąc do miesiąca (2020 - {{ date('Y') }})
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($monthToMonthComparison['years']) > 0)
                        <div style="position: relative; height: 500px;">
                            <canvas id="monthToMonthChart"></canvas>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-bar-chart fs-1 text-muted"></i>
                            <p class="text-muted mt-3">Brak danych do wyświetlenia.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Statystyki --}}
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title text-white-50 mb-2">
                                @if($filterType === 'range')
                                    Suma za okres
                                @else
                                    Suma za rok {{ $selectedYear }}
                                @endif
                            </h6>
                            <h2 class="mb-0">{{ number_format($totalForPeriod, 2, ',', ' ') }} zł</h2>
                            @if($filterType === 'range')
                                <small class="text-white-50">
                                    {{ $monthsCount }} {{ $monthsCount == 1 ? 'miesiąc' : ($monthsCount < 5 ? 'miesiące' : 'miesięcy') }}
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title text-white-50 mb-2">Średnia miesięczna</h6>
                            <h2 class="mb-0">{{ number_format($averageMonthly, 2, ',', ' ') }} zł</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title text-white-50 mb-2">Najlepszy miesiąc</h6>
                            @if($bestMonth && $bestMonth['amount'] > 0)
                                <h5 class="mb-1">
                                    @if($filterType === 'range')
                                        {{ $bestMonth['period_label'] }}
                                    @else
                                        {{ $bestMonth['month_name'] }}
                                    @endif
                                </h5>
                                <p class="mb-0 small">{{ number_format($bestMonth['amount'], 2, ',', ' ') }} zł</p>
                            @else
                                <p class="mb-0">Brak danych</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body">
                            <h6 class="card-title text-dark-50 mb-2">
                                @if($filterType === 'range')
                                    Trend (vs poprzedni okres)
                                @else
                                    Trend (vs {{ $selectedYear - 1 }})
                                @endif
                            </h6>
                            @if($trend != 0)
                                <h5 class="mb-1">
                                    @if($trend > 0)
                                        <i class="bi bi-arrow-up"></i> +{{ number_format($trend, 1) }}%
                                    @elseif($trend < 0)
                                        <i class="bi bi-arrow-down"></i> {{ number_format($trend, 1) }}%
                                    @else
                                        <i class="bi bi-dash"></i> 0%
                                    @endif
                                </h5>
                                <p class="mb-0 small">
                                    Poprzedni okres: {{ number_format($totalPreviousPeriod, 2, ',', ' ') }} zł
                                </p>
                            @else
                                <p class="mb-0">Brak danych</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Wykres --}}
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart me-2"></i>Wykres przychodów
                        @if($filterType === 'range')
                            - {{ $months[$startMonth] }} {{ $startYear }} - {{ $months[$endMonth] }} {{ $endYear }}
                        @else
                            - {{ $selectedYear }}
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($monthlyData) > 0 && array_sum(array_column($monthlyData, 'amount')) > 0)
                        <div style="position: relative; height: 400px;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-bar-chart fs-1 text-muted"></i>
                            <p class="text-muted mt-3">
                                @if($filterType === 'range')
                                    Brak danych do wyświetlenia dla wybranego zakresu dat.
                                @else
                                    Brak danych do wyświetlenia dla roku {{ $selectedYear }}.
                                @endif
                            </p>
                            <p class="text-muted">Dodaj rekordy przychodu w sekcji "Wprowadź dane".</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tabela szczegółowa --}}
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-table me-2"></i>Szczegóły miesięczne
                        @if($filterType === 'range')
                            - {{ $months[$startMonth] }} {{ $startYear }} - {{ $months[$endMonth] }} {{ $endYear }}
                        @else
                            - {{ $selectedYear }}
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Miesiąc</th>
                                    <th class="text-end">Kwota</th>
                                    <th class="text-end">% rocznej sumy</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalAmount = array_sum(array_column($monthlyData, 'amount'));
                                @endphp
                                @foreach($monthlyData as $data)
                                    <tr>
                                        <td>
                                            @if($filterType === 'range')
                                                {{ $data['period_label'] }}
                                            @else
                                                {{ $data['month_name'] }}
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold {{ $data['amount'] > 0 ? 'text-success' : 'text-muted' }}">
                                            {{ number_format($data['amount'], 2, ',', ' ') }} zł
                                        </td>
                                        <td class="text-end">
                                            @if($totalAmount > 0)
                                                {{ number_format(($data['amount'] / $totalAmount) * 100, 1) }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="table-primary fw-bold">
                                    <td>SUMA</td>
                                    <td class="text-end">{{ number_format($totalAmount, 2, ',', ' ') }} zł</td>
                                    <td class="text-end">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleFilterType() {
            const filterType = document.querySelector('input[name="filter_type"]:checked').value;
            const yearFilter = document.getElementById('yearFilter');
            const rangeFilter = document.getElementById('rangeFilter');
            
            if (filterType === 'year') {
                yearFilter.style.display = 'block';
                rangeFilter.style.display = 'none';
            } else {
                yearFilter.style.display = 'none';
                rangeFilter.style.display = 'block';
            }
        }

        // Wykres porównawczy miesiąc do miesiąca
        @if(count($monthToMonthComparison['years']) > 0)
        const monthToMonthCtx = document.getElementById('monthToMonthChart');
        if (monthToMonthCtx) {
            const comparisonData = @json($monthToMonthComparison['data']);
            const years = @json($monthToMonthComparison['years']);
            const currentYear = {{ date('Y') }};
            const previousYear = currentYear - 1;

            // Przygotuj etykiety (miesiące)
            const labels = comparisonData.map(item => item.month_name);

            // Przygotuj dane dla bieżącego roku
            const currentYearData = comparisonData.map(item => {
                const yearData = item.years.find(y => y.year === currentYear);
                return yearData ? parseFloat(yearData.amount) : 0;
            });

            // Przygotuj dane dla poprzedniego roku
            const previousYearData = comparisonData.map(item => {
                const yearData = item.years.find(y => y.year === previousYear);
                return yearData ? parseFloat(yearData.amount) : 0;
            });

            new Chart(monthToMonthCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Bieżący okres (' + currentYear + ')',
                            data: currentYearData,
                            backgroundColor: 'rgba(255, 193, 7, 0.8)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Poprzedni okres (' + previousYear + ')',
                            data: previousYearData,
                            backgroundColor: 'rgba(108, 117, 125, 0.6)',
                            borderColor: 'rgba(108, 117, 125, 1)',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString('pl-PL', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }) + ' zł';
                                }
                            }
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('pl-PL') + ' zł';
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
        }
        @endif

        @if(count($monthlyData) > 0 && array_sum(array_column($monthlyData, 'amount')) > 0)
        const ctx = document.getElementById('revenueChart');
        if (ctx) {
            const monthlyData = @json($monthlyData);
            const labels = monthlyData.map(item => {
                @if($filterType === 'range')
                    return item.period_label;
                @else
                    return item.month_name;
                @endif
            });
            const amounts = monthlyData.map(item => parseFloat(item.amount));

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Przychód (PLN)',
                        data: amounts,
                        backgroundColor: 'rgba(13, 110, 253, 0.6)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Przychód: ' + context.parsed.y.toLocaleString('pl-PL', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }) + ' zł';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('pl-PL') + ' zł';
                                }
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
