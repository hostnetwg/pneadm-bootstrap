<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Dashboard zamówień
            </h2>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    id="dashboardOrderSoundToggle"
                    title="Dźwięk przy nowym zamówieniu">
                <i class="bi bi-volume-up-fill" id="dashboardOrderSoundIcon" aria-hidden="true"></i>
                <span class="visually-hidden">Dźwięk przy nowym zamówieniu</span>
            </button>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if($liveVisitorsEnabled ?? false)
                <div class="card mb-4 border-secondary-subtle dashboard-refresh-surface" id="liveVisitorsCard">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="live-visitors-indicator" id="liveVisitorsIndicator" title="Odświeżanie na żywo">
                                <span class="live-visitors-indicator__ring" aria-hidden="true"></span>
                                <span class="live-visitors-indicator__dot" aria-hidden="true"></span>
                                <i class="bi bi-broadcast live-visitors-indicator__icon" aria-hidden="true"></i>
                            </span>
                            <h5 class="mb-0 fs-6">Aktywni teraz na pnedu.pl</h5>
                            <span class="badge text-bg-success" id="liveVisitorsCount">—</span>
                        </div>
                        <div class="small text-muted">
                            <span id="liveVisitorsMeta">Lejek sprzedaży · odświeżanie za {{ $liveVisitorsPollSeconds ?? 15 }} s</span>
                            @if(!empty($liveVisitorsDebugUrl))
                                · <a href="{{ $liveVisitorsDebugUrl }}" class="text-decoration-none">Debug eventów</a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sesja</th>
                                        <th>Wejście</th>
                                        <th>Ścieżka sesji</th>
                                        <th>Teraz</th>
                                        <th>Szkolenie</th>
                                        <th>Urządzenie</th>
                                        <th class="text-end">Ostatnio</th>
                                    </tr>
                                </thead>
                                <tbody id="liveVisitorsBody">
                                    <tr id="liveVisitorsLoading">
                                        <td colspan="7" class="text-muted small py-3 px-3">Ładowanie…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-3 py-2 border-top small text-muted d-flex align-items-center gap-2" id="liveVisitorsFooter">
                            <i class="bi bi-arrow-repeat live-visitors-footer-spin" id="liveVisitorsFooterSpin" aria-hidden="true"></i>
                            <span id="liveVisitorsFooterText">Odświeżono: —</span>
                        </div>
                    </div>
                </div>
                <style>
                    .live-visitors-indicator {
                        position: relative;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 1.75rem;
                        height: 1.75rem;
                        flex-shrink: 0;
                    }
                    .live-visitors-indicator__dot {
                        position: absolute;
                        width: 0.5rem;
                        height: 0.5rem;
                        border-radius: 50%;
                        background: var(--bs-success);
                        z-index: 2;
                    }
                    .live-visitors-indicator__ring {
                        position: absolute;
                        width: 0.5rem;
                        height: 0.5rem;
                        border-radius: 50%;
                        background: var(--bs-success);
                        opacity: 0.45;
                        animation: liveVisitorsPing 1.6s cubic-bezier(0, 0, 0.2, 1) infinite;
                    }
                    .live-visitors-indicator__icon {
                        font-size: 1.1rem;
                        color: var(--bs-success);
                        opacity: 0.85;
                    }
                    .live-visitors-indicator.is-fetching .live-visitors-indicator__icon {
                        animation: liveVisitorsSpin 0.75s linear infinite;
                    }
                    .live-visitors-indicator.is-fetching .live-visitors-indicator__ring {
                        animation: liveVisitorsSpin 0.75s linear infinite;
                        opacity: 0.35;
                    }
                    .live-visitors-footer-spin {
                        font-size: 0.85rem;
                        opacity: 0.45;
                        transition: opacity 0.2s ease;
                    }
                    .live-visitors-footer-spin.is-fetching {
                        opacity: 1;
                        animation: liveVisitorsSpin 0.75s linear infinite;
                    }
                    @keyframes liveVisitorsPing {
                        0% { transform: scale(1); opacity: 0.5; }
                        75%, 100% { transform: scale(2.6); opacity: 0; }
                    }
                    @keyframes liveVisitorsSpin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                </style>
            @endif

            <style>
                .dashboard-refresh-surface {
                    position: relative;
                    overflow: hidden;
                }
                .dashboard-refresh-surface::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    opacity: 0;
                    z-index: 5;
                    border-radius: inherit;
                    background-color: rgba(13, 110, 253, 0.22);
                }
                .dashboard-refresh-surface.is-refresh-flash::after {
                    animation: dashboardRefreshFlashFade 1.6s ease-out forwards;
                }
                @keyframes dashboardRefreshFlashFade {
                    0% { opacity: 0; }
                    18% { opacity: 0.42; }
                    100% { opacity: 0; }
                }
            </style>

            <div class="row mb-4 g-3" id="dashboardOrdersStatsRow">
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-primary h-100 dashboard-refresh-surface">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-1">Dziś (FORM)</h6>
                                    <h2 class="mb-0 text-primary" id="dashboardStatFormToday" data-initial-value="{{ $stats['form_today'] }}">{{ number_format($stats['form_today']) }}</h2>
                                    <small class="text-muted">wczoraj: <span id="dashboardStatFormYesterday">{{ number_format($stats['form_yesterday']) }}</span></small>
                                </div>
                                <i class="bi bi-calendar-check fs-2 text-primary opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-warning h-100 dashboard-refresh-surface">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-1">Do obsługi</h6>
                                    <h2 class="mb-0 text-warning" id="dashboardStatFormHandling">{{ number_format($stats['form_handling']) }}</h2>
                                    <small class="text-muted">brak FV lub dostępu · aktywne szkolenia</small>
                                </div>
                                <i class="bi bi-inbox fs-2 text-warning opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ route('form-orders.index', ['quick' => 'handling', 'settlement' => 'deferred']) }}" class="text-decoration-none">
                        <div class="card border-success h-100 dashboard-refresh-surface">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-1">Odroczna płatność</h6>
                                        <h2 class="mb-0 text-success" id="dashboardStatDeferredHandling">{{ number_format($stats['deferred_handling']) }}</h2>
                                        <small class="text-muted">do obsługi · odroczona faktura</small>
                                    </div>
                                    <i class="bi bi-receipt fs-2 text-success opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ route('form-orders.index', ['quick' => 'handling', 'settlement' => 'online']) }}" class="text-decoration-none">
                        <div class="card border-info h-100 dashboard-refresh-surface">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-1">Płatności online</h6>
                                        <h2 class="mb-0 text-info" id="dashboardStatOnlineHandling">{{ number_format($stats['online_handling']) }}</h2>
                                        <small class="text-muted">do obsługi · bramka płatności</small>
                                    </div>
                                    <i class="bi bi-credit-card fs-2 text-info opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="card mb-4 dashboard-refresh-surface" id="dashboardChartCard">
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
                                   class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   data-dashboard-date-preset="1"
                                   data-date-from="{{ $preset['date_from'] }}"
                                   data-date-to="{{ $preset['date_to'] }}"
                                   @if(($preset['key'] ?? '') === '14d') data-default-range-preset="1" @endif>
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
                                   class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   data-dashboard-date-preset="1"
                                   data-date-from="{{ $preset['date_from'] }}"
                                   data-date-to="{{ $preset['date_to'] }}">
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

                    <div class="d-flex flex-wrap gap-3 mb-3 small text-muted" id="dashboardPeriodSummary">
                        <span>
                            Okres:
                            <strong id="dashboardOkresFrom">{{ $filters['date_from'] }}</strong>
                            →
                            <strong id="dashboardOkresTo">{{ $filters['date_to'] }}</strong>
                            ({{ $tz }})
                        </span>
                        <span>Łącznie: <strong id="dashboardPeriodTotal">{{ number_format($stats['period_total']) }}</strong></span>
                        <span>Online: <strong id="dashboardPeriodOnline">{{ number_format($stats['period_online']) }}</strong></span>
                        <span>Odroczone: <strong id="dashboardPeriodDeferred">{{ number_format($stats['period_deferred']) }}</strong></span>
                        <span>Średnio/<span id="dashboardPeriodAvgLabel">{{ $stats['period_avg_label'] ?? 'dzień' }}</span>: <strong id="dashboardPeriodAvg">{{ number_format($stats['period_avg'], 1, ',', ' ') }}</strong></span>
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
                    <div class="card h-100 dashboard-refresh-surface" id="dashboardRecentOrdersCard">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Ostatnie zamówienia FORM</h5>
                            <a href="{{ route('form-orders.index') }}" class="btn btn-sm btn-outline-primary">
                                Pełna lista
                            </a>
                        </div>
                        <div class="card-body p-0" id="dashboardRecentOrdersContainer">
                            @if($recentFormOrders->isEmpty())
                                <p class="text-muted p-3 mb-0" id="dashboardRecentOrdersEmpty">Brak zamówień do wyświetlenia.</p>
                            @else
                                <div class="table-responsive" id="dashboardRecentOrdersTableWrap">
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
                                        <tbody id="dashboardRecentOrdersBody">
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
                    <div class="card h-100 dashboard-refresh-surface" id="dashboardShortcutsCard">
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
                                <span class="badge text-bg-warning rounded-pill" id="dashboardShortcutHandling">{{ $stats['form_handling'] }}</span>
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

    @php
        $dashboardDateKey = \Carbon\Carbon::today($tz)->toDateString();
    @endphp

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
            const labelFontSize = labels.length > 31 ? 9 : 11;
            window.dashboardChartFullLabels = @json($dailyChart['labels'] ?? []);

            if (!window.dashboardOrdersTotalLabelsPluginRegistered) {
                Chart.register({
                    id: 'dashboardOrdersTotalLabels',
                    afterDatasetsDraw: function (chart) {
                        if (chart.canvas.id !== 'ordersDailyChart') {
                            return;
                        }

                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);

                        if (!dataset || !meta || meta.hidden) {
                            return;
                        }

                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.font = '600 ' + (window.dashboardChartLabelFontSize || 11) + 'px system-ui, sans-serif';
                        ctx.fillStyle = 'rgba(13, 110, 253, 1)';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';

                        meta.data.forEach(function (point, index) {
                            if (!point || typeof point.x !== 'number' || typeof point.y !== 'number') {
                                return;
                            }

                            const value = dataset.data[index];
                            if (value === null || value === undefined) {
                                return;
                            }

                            ctx.fillText(String(value), point.x, point.y - 6);
                        });

                        ctx.restore();
                    },
                });
                window.dashboardOrdersTotalLabelsPluginRegistered = true;
            }

            window.dashboardChartLabelFontSize = labelFontSize;

            window.ordersDailyChartInstance = new Chart(canvas, {
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
                                    const fullLabels = window.dashboardChartFullLabels || [];

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

        (function () {
            const STORAGE_KEY = 'dashboard_new_order_sound_enabled';
            let audioCtx = null;
            let soundEnabled = localStorage.getItem(STORAGE_KEY) !== 'false';

            function getAudioContext() {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) {
                    return null;
                }

                if (!audioCtx) {
                    audioCtx = new AudioContextClass();
                }

                return audioCtx;
            }

            function unlockAudio() {
                try {
                    const ctx = getAudioContext();
                    if (ctx && ctx.state === 'suspended') {
                        ctx.resume();
                    }
                } catch (e) {
                    // fail-silent
                }
            }

            function playTone(ctx, frequency, startAt, duration) {
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.value = frequency;
                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(0.14, startAt + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);
                oscillator.connect(gain);
                gain.connect(ctx.destination);
                oscillator.start(startAt);
                oscillator.stop(startAt + duration + 0.05);
            }

            function playNewOrderChime() {
                if (!soundEnabled) {
                    return;
                }

                try {
                    const ctx = getAudioContext();
                    if (!ctx) {
                        return;
                    }

                    if (ctx.state === 'suspended') {
                        ctx.resume().then(function () {
                            playNewOrderChime();
                        });

                        return;
                    }

                    const start = ctx.currentTime;
                    playTone(ctx, 523.25, start, 0.28);
                    playTone(ctx, 659.25, start + 0.14, 0.32);
                } catch (e) {
                    // fail-silent
                }
            }

            window.dashboardPlayNewOrderSound = function () {
                playNewOrderChime();
            };

            document.addEventListener('click', unlockAudio, { once: true, passive: true });
            document.addEventListener('keydown', unlockAudio, { once: true });

            const toggleBtn = document.getElementById('dashboardOrderSoundToggle');
            const toggleIcon = document.getElementById('dashboardOrderSoundIcon');

            function updateToggleUi() {
                if (!toggleBtn || !toggleIcon) {
                    return;
                }

                toggleBtn.classList.toggle('btn-outline-secondary', soundEnabled);
                toggleBtn.classList.toggle('btn-outline-warning', !soundEnabled);
                toggleIcon.className = soundEnabled ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute-fill';
                toggleBtn.setAttribute(
                    'title',
                    soundEnabled ? 'Wyłącz dźwięk przy nowym zamówieniu' : 'Włącz dźwięk przy nowym zamówieniu'
                );
            }

            if (toggleBtn) {
                updateToggleUi();
                toggleBtn.addEventListener('click', function () {
                    soundEnabled = !soundEnabled;
                    localStorage.setItem(STORAGE_KEY, soundEnabled ? 'true' : 'false');
                    updateToggleUi();
                    unlockAudio();

                    if (soundEnabled) {
                        playNewOrderChime();
                    }
                });
            }
        })();

        (function () {
            var flashDebounceTimer = null;

            window.dashboardTriggerRefreshFlash = function () {
                if (flashDebounceTimer) {
                    clearTimeout(flashDebounceTimer);
                }

                flashDebounceTimer = setTimeout(function () {
                    flashDebounceTimer = null;

                    document.querySelectorAll('.dashboard-refresh-surface').forEach(function (el) {
                        el.classList.remove('is-refresh-flash');
                        void el.offsetWidth;
                        el.classList.add('is-refresh-flash');
                        window.setTimeout(function () {
                            el.classList.remove('is-refresh-flash');
                        }, 1600);
                    });
                }, 60);
            };
        })();

        (function () {
            const pollUrl = @json(route('api.dashboard.orders-stats'));
            const pollSeconds = {{ (int) ($dashboardPollSeconds ?? 15) }};
            const appTimezone = @json($tz);
            const dashboardBaseUrl = @json(route('dashboard'));
            const defaultRangeDays = 14;
            let chartDateFrom = @json($filters['date_from'] ?? '');
            let chartDateTo = @json($filters['date_to'] ?? '');
            let trackedDateKey = @json($dashboardDateKey);
            const formTodayEl = document.getElementById('dashboardStatFormToday');
            const formYesterdayEl = document.getElementById('dashboardStatFormYesterday');
            const formHandlingEl = document.getElementById('dashboardStatFormHandling');
            const deferredHandlingEl = document.getElementById('dashboardStatDeferredHandling');
            const onlineHandlingEl = document.getElementById('dashboardStatOnlineHandling');
            const periodTotalEl = document.getElementById('dashboardPeriodTotal');
            const periodOnlineEl = document.getElementById('dashboardPeriodOnline');
            const periodDeferredEl = document.getElementById('dashboardPeriodDeferred');
            const periodAvgEl = document.getElementById('dashboardPeriodAvg');
            const periodAvgLabelEl = document.getElementById('dashboardPeriodAvgLabel');
            const recentOrdersContainer = document.getElementById('dashboardRecentOrdersContainer');
            const shortcutHandlingEl = document.getElementById('dashboardShortcutHandling');
            let pollCount = 0;
            let lastFormToday = parseInt(formTodayEl?.dataset.initialValue || '0', 10);
            let sectionsFetchInFlight = false;

            if (!formTodayEl || !formHandlingEl) {
                return;
            }

            function todayDateKeyInAppTz() {
                return new Intl.DateTimeFormat('en-CA', { timeZone: appTimezone }).format(new Date());
            }

            function shiftDateKeyInAppTz(dateKey, dayDelta) {
                const parts = String(dateKey).split('-').map(Number);
                const anchor = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2], 12, 0, 0));
                anchor.setUTCDate(anchor.getUTCDate() + dayDelta);

                return new Intl.DateTimeFormat('en-CA', { timeZone: appTimezone }).format(anchor);
            }

            function defaultChartDateRange() {
                const dateTo = todayDateKeyInAppTz();

                return {
                    dateFrom: shiftDateKeyInAppTz(dateTo, -(defaultRangeDays - 1)),
                    dateTo: dateTo,
                };
            }

            function updateDateFilterUi(dateFrom, dateTo) {
                const fromInput = document.getElementById('date_from');
                const toInput = document.getElementById('date_to');
                const okresFrom = document.getElementById('dashboardOkresFrom');
                const okresTo = document.getElementById('dashboardOkresTo');

                if (fromInput) {
                    fromInput.value = dateFrom;
                }
                if (toInput) {
                    toInput.value = dateTo;
                }
                if (okresFrom) {
                    okresFrom.textContent = dateFrom;
                }
                if (okresTo) {
                    okresTo.textContent = dateTo;
                }

                document.querySelectorAll('[data-dashboard-date-preset]').forEach(function (btn) {
                    const isActive = btn.getAttribute('data-date-from') === dateFrom
                        && btn.getAttribute('data-date-to') === dateTo;

                    btn.classList.toggle('btn-primary', isActive);
                    btn.classList.toggle('btn-outline-secondary', !isActive);
                });
            }

            function clearUrlDateFilters() {
                if (window.history && typeof window.history.replaceState === 'function') {
                    window.history.replaceState(null, '', dashboardBaseUrl);
                }
            }

            function resetDashboardForNewDay() {
                const range = defaultChartDateRange();

                trackedDateKey = todayDateKeyInAppTz();
                chartDateFrom = range.dateFrom;
                chartDateTo = range.dateTo;
                updateDateFilterUi(chartDateFrom, chartDateTo);
                clearUrlDateFilters();

                return fetchDashboardSections();
            }

            function maybeRefreshForNewDay() {
                if (todayDateKeyInAppTz() === trackedDateKey) {
                    return Promise.resolve(false);
                }

                return resetDashboardForNewDay().then(function () {
                    return true;
                });
            }

            function formatCount(value) {
                return new Intl.NumberFormat('pl-PL').format(Number(value) || 0);
            }

            function formatDecimal(value) {
                return new Intl.NumberFormat('pl-PL', {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                }).format(Number(value) || 0);
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function renderHeadlineStats(data) {
                formTodayEl.textContent = formatCount(data.form_today);
                if (formYesterdayEl) {
                    formYesterdayEl.textContent = formatCount(data.form_yesterday);
                }
                if (formHandlingEl) {
                    formHandlingEl.textContent = formatCount(data.form_handling);
                }
                if (deferredHandlingEl) {
                    deferredHandlingEl.textContent = formatCount(data.deferred_handling);
                }
                if (onlineHandlingEl) {
                    onlineHandlingEl.textContent = formatCount(data.online_handling);
                }
                if (shortcutHandlingEl) {
                    shortcutHandlingEl.textContent = formatCount(data.form_handling);
                }
            }

            function updateChart(chartData) {
                const chart = window.ordersDailyChartInstance;
                if (!chart || !chartData) {
                    return;
                }

                const labels = Array.isArray(chartData.labels_short) ? chartData.labels_short : [];
                window.dashboardChartFullLabels = Array.isArray(chartData.labels) ? chartData.labels : [];
                window.dashboardChartLabelFontSize = labels.length > 31 ? 9 : 11;

                chart.data.labels = labels;
                chart.data.datasets[0].data = chartData.total || [];
                chart.data.datasets[1].data = chartData.online || [];
                chart.data.datasets[2].data = chartData.deferred || [];

                const nextRadius = labels.length > 31 ? 2 : 4;
                chart.data.datasets.forEach(function (dataset) {
                    dataset.pointRadius = nextRadius;
                });

                chart.update();
            }

            function renderRecentOrders(orders) {
                if (!recentOrdersContainer) {
                    return;
                }

                const items = Array.isArray(orders) ? orders : [];

                if (items.length === 0) {
                    recentOrdersContainer.innerHTML = '<p class="text-muted p-3 mb-0" id="dashboardRecentOrdersEmpty">Brak zamówień do wyświetlenia.</p>';

                    return;
                }

                const rows = items.map(function (order) {
                    return '<tr>'
                        + '<td><code>' + escapeHtml(order.ident) + '</code></td>'
                        + '<td class="text-truncate" style="max-width: 220px;" title="' + escapeHtml(order.course_title || '—') + '">'
                        + escapeHtml(order.course_title || '—') + '</td>'
                        + '<td>' + escapeHtml(order.order_date || '—') + '</td>'
                        + '<td class="text-end">' + escapeHtml(order.product_price || '—') + '</td>'
                        + '<td class="text-end"><a href="' + escapeHtml(order.show_url) + '" class="btn btn-sm btn-link">Szczegóły</a></td>'
                        + '</tr>';
                }).join('');

                recentOrdersContainer.innerHTML = ''
                    + '<div class="table-responsive" id="dashboardRecentOrdersTableWrap">'
                    + '<table class="table table-hover table-sm mb-0 align-middle">'
                    + '<thead class="table-light"><tr>'
                    + '<th>Ident</th><th>Szkolenie</th><th>Data</th><th class="text-end">Kwota</th><th></th>'
                    + '</tr></thead>'
                    + '<tbody id="dashboardRecentOrdersBody">' + rows + '</tbody>'
                    + '</table></div>';
            }

            function renderSections(sections) {
                if (!sections) {
                    return;
                }

                if (sections.period) {
                    if (periodTotalEl) {
                        periodTotalEl.textContent = formatCount(sections.period.total);
                    }
                    if (periodOnlineEl) {
                        periodOnlineEl.textContent = formatCount(sections.period.online);
                    }
                    if (periodDeferredEl) {
                        periodDeferredEl.textContent = formatCount(sections.period.deferred);
                    }
                    if (periodAvgEl) {
                        periodAvgEl.textContent = formatDecimal(sections.period.avg);
                    }
                    if (periodAvgLabelEl && sections.period.avg_label) {
                        periodAvgLabelEl.textContent = sections.period.avg_label;
                    }
                }

                if (sections.chart) {
                    updateChart(sections.chart);
                }

                if (sections.recent_orders) {
                    renderRecentOrders(sections.recent_orders);
                }

                if (sections.shortcuts && shortcutHandlingEl) {
                    shortcutHandlingEl.textContent = formatCount(sections.shortcuts.form_handling);
                }
            }

            function fetchDashboardSections() {
                if (sectionsFetchInFlight) {
                    return Promise.resolve();
                }

                sectionsFetchInFlight = true;

                const params = new URLSearchParams({
                    sections: '1',
                    date_from: chartDateFrom,
                    date_to: chartDateTo,
                });

                return fetch(pollUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        renderHeadlineStats(data);
                        lastFormToday = Number(data.form_today) || 0;
                        formTodayEl.dataset.initialValue = String(lastFormToday);
                        renderSections(data.sections);

                        if (typeof window.dashboardTriggerRefreshFlash === 'function') {
                            window.dashboardTriggerRefreshFlash();
                        }
                    })
                    .catch(function () {
                        // fail-silent
                    })
                    .finally(function () {
                        sectionsFetchInFlight = false;
                    });
            }

            function refreshOrdersStats() {
                maybeRefreshForNewDay()
                    .then(function (dayChanged) {
                        if (dayChanged) {
                            pollCount += 1;

                            return;
                        }

                        return fetch(pollUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        })
                            .then(function (response) {
                                if (!response.ok) {
                                    throw new Error('HTTP ' + response.status);
                                }

                                return response.json();
                            })
                            .then(function (data) {
                                const previousFormToday = lastFormToday;
                                const nextFormToday = Number(data.form_today) || 0;

                                renderHeadlineStats(data);
                                pollCount += 1;

                                if (nextFormToday > previousFormToday) {
                                    lastFormToday = nextFormToday;
                                    formTodayEl.dataset.initialValue = String(lastFormToday);

                                    if (typeof window.dashboardPlayNewOrderSound === 'function') {
                                        window.dashboardPlayNewOrderSound();
                                    }

                                    return fetchDashboardSections();
                                }

                                if (nextFormToday !== previousFormToday) {
                                    lastFormToday = nextFormToday;
                                    formTodayEl.dataset.initialValue = String(lastFormToday);
                                }

                                if (pollCount > 1 && typeof window.dashboardTriggerRefreshFlash === 'function') {
                                    window.dashboardTriggerRefreshFlash();
                                }
                            });
                    })
                    .catch(function () {
                        // fail-silent — pierwsze wartości z SSR pozostają widoczne
                    });
            }

            refreshOrdersStats();
            setInterval(refreshOrdersStats, pollSeconds * 1000);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') {
                    refreshOrdersStats();
                }
            });
        })();

        @if($liveVisitorsEnabled ?? false)
        (function () {
            const pollUrl = @json(route('api.dashboard.live-visitors'));
            const pollSeconds = {{ (int) ($liveVisitorsPollSeconds ?? 30) }};
            const countEl = document.getElementById('liveVisitorsCount');
            const bodyEl = document.getElementById('liveVisitorsBody');
            const footerEl = document.getElementById('liveVisitorsFooterText');
            const footerSpinEl = document.getElementById('liveVisitorsFooterSpin');
            const indicatorEl = document.getElementById('liveVisitorsIndicator');
            const metaEl = document.getElementById('liveVisitorsMeta');
            let pollCount = 0;
            let windowMinutes = null;
            let countdownSeconds = pollSeconds;
            let countdownTimer = null;
            let refreshInFlight = false;

            if (!countEl || !bodyEl || !footerEl) {
                return;
            }

            function updateMetaText() {
                if (!metaEl) {
                    return;
                }

                var windowPart = windowMinutes ? (' · okno ' + windowMinutes + ' min') : '';
                metaEl.textContent = 'Lejek sprzedaży' + windowPart + ' · odświeżanie za ' + countdownSeconds + ' s';
            }

            function resetCountdown() {
                countdownSeconds = pollSeconds;
                updateMetaText();
            }

            function startCountdown() {
                if (countdownTimer) {
                    clearInterval(countdownTimer);
                }

                countdownTimer = setInterval(function () {
                    if (refreshInFlight) {
                        return;
                    }

                    countdownSeconds -= 1;

                    if (countdownSeconds <= 0) {
                        countdownSeconds = 0;
                        updateMetaText();
                        refreshLiveVisitors();

                        return;
                    }

                    updateMetaText();
                }, 1000);
            }

            function setFetching(isFetching) {
                if (indicatorEl) {
                    indicatorEl.classList.toggle('is-fetching', isFetching);
                }
                if (footerSpinEl) {
                    footerSpinEl.classList.toggle('is-fetching', isFetching);
                }
            }

            function formatAgo(seconds) {
                if (seconds < 60) {
                    return seconds + ' s temu';
                }
                if (seconds < 3600) {
                    return Math.floor(seconds / 60) + ' min temu';
                }

                return Math.floor(seconds / 3600) + ' h temu';
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function deviceLabel(visitor) {
                const parts = [];
                if (visitor.device_type) {
                    parts.push(visitor.device_type);
                }
                if (visitor.browser_family) {
                    parts.push(visitor.browser_family);
                }

                return parts.length ? parts.join(' · ') : '—';
            }

            function pageLabelCell(visitor) {
                var label = escapeHtml(visitor.page_label || '—');

                if (visitor.form_order_id) {
                    var orderLink = visitor.form_order_url
                        ? ' <a href="' + escapeHtml(visitor.form_order_url) + '" class="small text-decoration-none fw-semibold">#'
                            + escapeHtml(String(visitor.form_order_id)) + '</a>'
                        : '';

                    return '<span class="badge text-bg-success">' + label + '</span>' + orderLink;
                }

                return label;
            }

            function entryCell(visitor) {
                var parts = [];

                if (visitor.entry_referrer_domain) {
                    parts.push(escapeHtml(visitor.entry_referrer_domain));
                }
                if (visitor.entry_campaign_code) {
                    parts.push('kamp. ' + escapeHtml(visitor.entry_campaign_code));
                }

                return parts.length ? parts.join(' · ') : '—';
            }

            function sessionCell(visitor) {
                var shortId = visitor.session_short || '—';
                var count = Number(visitor.session_event_count) || 0;

                if (count > 1) {
                    return '<code class="small">' + escapeHtml(shortId) + ' (' + count + ')</code>';
                }

                return '<code class="small">' + escapeHtml(shortId) + '</code>';
            }

            function journeyCell(visitor) {
                var label = visitor.journey_label || '—';

                if (Array.isArray(visitor.journey_steps) && visitor.journey_steps.length > 0) {
                    label = visitor.journey_steps.map(function (step) {
                        return step.label || '—';
                    }).join(' → ');
                }

                return '<span class="small text-primary" title="' + escapeHtml(label) + '">'
                    + escapeHtml(label) + '</span>';
            }

            function renderVisitors(data) {
                countEl.textContent = String(data.active_count ?? 0);

                if (data.window_minutes) {
                    windowMinutes = data.window_minutes;
                }
                updateMetaText();

                const visitors = Array.isArray(data.visitors) ? data.visitors : [];

                if (visitors.length === 0) {
                    bodyEl.innerHTML = '<tr><td colspan="7" class="text-muted small py-3 px-3">Brak aktywnych sesji w ostatnich minutach.</td></tr>';
                } else {
                    bodyEl.innerHTML = visitors.map(function (visitor) {
                        return '<tr>'
                            + '<td>' + sessionCell(visitor) + '</td>'
                            + '<td class="small text-muted">' + entryCell(visitor) + '</td>'
                            + '<td class="text-truncate" style="max-width: 280px;">' + journeyCell(visitor) + '</td>'
                            + '<td>' + pageLabelCell(visitor) + '</td>'
                            + '<td class="text-truncate" style="max-width: 200px;" title="' + escapeHtml(visitor.course_title || '') + '">'
                            + escapeHtml(visitor.course_title || '—') + '</td>'
                            + '<td class="small text-muted">' + escapeHtml(deviceLabel(visitor)) + '</td>'
                            + '<td class="text-end small text-nowrap">' + escapeHtml(formatAgo(visitor.last_seen_ago_seconds ?? 0)) + '</td>'
                            + '</tr>';
                    }).join('');
                }

                const asOf = data.as_of ? new Date(data.as_of) : new Date();
                footerEl.textContent = 'Odświeżono: ' + asOf.toLocaleTimeString('pl-PL');
            }

            function refreshLiveVisitors() {
                if (refreshInFlight) {
                    return;
                }

                refreshInFlight = true;
                setFetching(true);

                fetch(pollUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        renderVisitors(data);
                        pollCount += 1;
                        if (pollCount > 1 && typeof window.dashboardTriggerRefreshFlash === 'function') {
                            window.dashboardTriggerRefreshFlash();
                        }
                    })
                    .catch(function () {
                        bodyEl.innerHTML = '<tr><td colspan="7" class="text-danger small py-3 px-3">Nie udało się pobrać danych o aktywnych sesjach.</td></tr>';
                    })
                    .finally(function () {
                        setFetching(false);
                        refreshInFlight = false;
                        resetCountdown();
                    });
            }

            resetCountdown();
            startCountdown();
            refreshLiveVisitors();
        })();
        @endif
    </script>
    @endpush
</x-app-layout>
