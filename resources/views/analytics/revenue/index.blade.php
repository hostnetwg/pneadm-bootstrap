<x-app-layout>
    @php
        $timezone = $meta['timezone'] ?? 'Europe/Warsaw';
        $formatNumber = fn (int|float $value): string => number_format((float) $value, 0, ',', ' ');
        $formatMoney = fn (int|float $value): string => number_format((float) $value, 2, ',', ' ').' PLN';
        $hasCourseLink = \Illuminate\Support\Facades\Route::has('courses.show');
        $hasCampaignLink = \Illuminate\Support\Facades\Route::has('marketing-campaigns.show');
        $exportQuery = array_filter([
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'course_id' => $filters['course_id'] ?? null,
            'campaign_code' => $filters['campaign_code'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Analityka — Rozliczenia
            </h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form id="recomputeRevenueForm" method="POST" action="{{ route('analytics.revenue.recompute') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    <input type="hidden" name="course_id" value="{{ $filters['course_id'] ?? '' }}">
                    <input type="hidden" name="campaign_code" value="{{ $filters['campaign_code'] ?? '' }}">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recomputeRevenueModal">
                        <i class="bi bi-arrow-repeat"></i> Przelicz rozliczenia
                    </button>
                </form>
                <a href="{{ route('analytics.revenue.export.courses', $exportQuery) }}" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv"></i> Eksport CSV — kursy
                </a>
                <a href="{{ route('analytics.revenue.export.campaigns', $exportQuery) }}" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv"></i> Eksport CSV — kampanie
                </a>
                <a href="{{ route('analytics.revenue.export.daily', $exportQuery) }}" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv"></i> Eksport CSV — dziennie
                </a>
                @if(\Illuminate\Support\Facades\Route::has('analytics.sales-funnel.index'))
                    <a href="{{ route('analytics.sales-funnel.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-funnel"></i> Lejek sprzedaży
                    </a>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('analytics.form-abandonments.index'))
                    <a href="{{ route('analytics.form-abandonments.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-ui-checks"></i> Porzucenia formularza
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid px-0">
            @includeIf('analytics.partials.status-banner', ['showSettingsLink' => true])

            @if(session('recompute_status'))
                <div class="alert alert-success alert-dismissible fade show small" role="alert">
                    {{ session('recompute_status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif

            @if(session('recompute_error'))
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    {{ session('recompute_error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif

            <div class="modal fade" id="recomputeRevenueModal" tabindex="-1" aria-labelledby="recomputeRevenueModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="recomputeRevenueModalLabel">
                                <i class="bi bi-arrow-repeat"></i> Przeliczyć rozliczenia?
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Przeliczę dzienne agregaty rozliczeń (R1) dla wybranego zakresu dat:</p>
                            <p class="mb-3 fs-6">
                                <span class="badge bg-primary-subtle text-primary-emphasis">{{ $filters['date_from'] ?? '—' }}</span>
                                <span class="text-muted mx-1">→</span>
                                <span class="badge bg-primary-subtle text-primary-emphasis">{{ $filters['date_to'] ?? '—' }}</span>
                            </p>
                            <p class="text-muted small mb-2">
                                Operacja jest bezpieczna i można ją powtarzać (idempotentna) — kasuje i liczy od zera per dzień.
                                Nie zmienia surowych eventów ani danych sprzedażowych.
                            </p>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle"></i>
                                Maksymalny zakres na jedno przeliczenie:
                                <strong>{{ (int) config('analytics.revenue_dashboard.recompute_max_days', 92) }}</strong> dni.
                                Dla większych zakresów użyj komendy <code>analytics:aggregate-revenue</code> w konsoli.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" form="recomputeRevenueForm" class="btn btn-primary btn-sm">
                                <i class="bi bi-arrow-repeat"></i> Tak, przelicz
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info small" role="alert">
                <strong>Dashboard rozliczeń (read-only).</strong>
                Dane pochodzą wyłącznie z dziennych agregatów <code>pne_analytics</code>
                (<code>analytics_daily_course_revenue_stats</code>, <code>analytics_daily_campaign_revenue_stats</code>).
                Strefa czasu: <strong>{{ $timezone }}</strong>, lag agregacji: <strong>{{ (int) ($meta['lag_days'] ?? 1) }} dni</strong>.
                Bez danych osobowych.
            </div>

            <div class="alert alert-warning small" role="alert">
                <i class="bi bi-calendar-event"></i>
                <strong>Model dat:</strong>
                <em>Zamówione</em> wg daty utworzenia zamówienia (<code>form_order_created</code>);
                <em>Opłacone online</em> wg daty potwierdzenia płatności (<code>payment_status_changed: paid</code>);
                <em>Zafakturowane odroczone</em> wg daty wystawienia faktury (<code>invoice_created</code>, odroczone).
                <em>Rozliczone łącznie</em> = opłaty online + faktury odroczone (faktura online to tylko znacznik księgowy, poza rozliczeniem).
                Metryki w jednym zakresie dat <strong>nie tworzą jednego lejka</strong> — każda kolumna ma własną datę źródłową.
            </div>

            {{-- Filtry --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-funnel"></i> Filtry</span>
                </div>
                <div class="card-body">
                    @php
                        $presetBase = array_filter([
                            'course_id' => $filters['course_id'] ?? null,
                            'campaign_code' => $filters['campaign_code'] ?? null,
                        ], fn ($v) => $v !== null && $v !== '');
                    @endphp
                    @if(!empty($date_presets ?? []))
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            <span class="small text-muted me-1 align-self-center">Szybki zakres:</span>
                            @foreach($date_presets as $preset)
                                @php
                                    $isActive = ($filters['date_from'] ?? null) === $preset['date_from']
                                        && ($filters['date_to'] ?? null) === $preset['date_to'];
                                @endphp
                                <a href="{{ route('analytics.revenue.index', array_merge($presetBase, ['date_from' => $preset['date_from'], 'date_to' => $preset['date_to']])) }}"
                                   class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    {{ $preset['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <form method="GET" action="{{ route('analytics.revenue.index') }}" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label for="date_from" class="form-label small">Od</label>
                            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label small">Do</label>
                            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label for="campaign_code" class="form-label small">Campaign code</label>
                            <input type="text" class="form-control form-control-sm" id="campaign_code" name="campaign_code" value="{{ $filters['campaign_code'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label for="course_id" class="form-label small">Course ID</label>
                            <input type="number" class="form-control form-control-sm" id="course_id" name="course_id" value="{{ $filters['course_id'] ?? '' }}">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="bi bi-search"></i> Filtruj
                            </button>
                            <a href="{{ route('analytics.revenue.index') }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                        </div>
                    </form>
                    <p class="small text-muted mb-0 mt-2">
                        Domyślny zakres: ostatnie {{ (int) ($meta['default_days'] ?? 14) }} dni zakończone na dniu dojrzałym
                        (dziś − {{ (int) ($meta['lag_days'] ?? 1) }}). Maksymalny zakres: {{ (int) ($meta['max_days'] ?? 366) }} dni.
                        Przeliczenie agregatów: przycisk <strong>Przelicz rozliczenia</strong> lub komenda <code>analytics:aggregate-revenue</code> (konsola / cron).
                    </p>
                </div>
            </div>

            @if(!empty($comparison['previous_period'] ?? null))
                <p class="small text-muted mb-2">
                    <i class="bi bi-arrow-left-right"></i>
                    Porównanie z poprzednim okresem o tej samej długości:
                    <strong>{{ $comparison['previous_period']['date_from'] }}</strong> – <strong>{{ $comparison['previous_period']['date_to'] }}</strong>
                    ({{ (int) $comparison['previous_period']['days'] }} dni).
                </p>
            @endif

            {{-- Kafelki podsumowania --}}
            <div class="row g-3 mb-3">
                <div class="col-lg-3 col-md-6">
                    <div class="card shadow-sm h-100 border-start border-primary border-3">
                        <div class="card-body">
                            <div class="small text-muted">Zamówione</div>
                            <div class="fs-4 fw-semibold">{{ $formatNumber($summary['orders_created']) }}</div>
                            <div class="fs-6 text-muted">{{ $formatMoney($summary['ordered_revenue_gross']) }}</div>
                            @if(!empty($comparison))
                                @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'orders_created'])
                                @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'ordered_revenue_gross', 'type' => 'money'])
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card shadow-sm h-100 border-start border-success border-3">
                        <div class="card-body">
                            <div class="small text-muted">Opłacone online</div>
                            <div class="fs-4 fw-semibold">{{ $formatNumber($summary['online_paid_orders']) }}</div>
                            <div class="fs-6 text-muted">{{ $formatMoney($summary['online_paid_revenue_gross']) }}</div>
                            @if(!empty($comparison))
                                @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'online_paid_orders'])
                                @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'online_paid_revenue_gross', 'type' => 'money'])
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card shadow-sm h-100 border-start border-info border-3">
                        <div class="card-body">
                            <div class="small text-muted">Zafakturowane odroczone</div>
                            <div class="fs-4 fw-semibold">{{ $formatNumber($summary['deferred_invoiced_orders']) }}</div>
                            <div class="fs-6 text-muted">{{ $formatMoney($summary['deferred_invoiced_revenue_gross']) }}</div>
                            @if(!empty($comparison))
                                @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'deferred_invoiced_orders'])
                                @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'deferred_invoiced_revenue_gross', 'type' => 'money'])
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card shadow-sm h-100 border-start border-dark border-3">
                        <div class="card-body">
                            <div class="small text-muted">Rozliczone łącznie</div>
                            <div class="fs-4 fw-semibold">{{ $formatNumber($summary['settled_orders_total']) }}</div>
                            <div class="fs-6 text-muted">{{ $formatMoney($summary['settled_revenue_gross']) }}</div>
                            @if(!empty($comparison))
                                @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'settled_orders_total'])
                                @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'settled_revenue_gross', 'type' => 'money'])
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if((int) ($summary['online_invoiced_marker_orders'] ?? 0) > 0)
                <p class="small text-muted mb-3">
                    <i class="bi bi-receipt"></i>
                    Znacznik księgowy (faktura online, poza rozliczeniem):
                    <strong>{{ $formatNumber($summary['online_invoiced_marker_orders']) }}</strong> zamówień.
                    @if(!empty($comparison))
                        @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'online_invoiced_marker_orders'])
                    @endif
                </p>
            @endif

            @if(
                (int) ($summary['orders_created_without_campaign'] ?? 0) > 0
                || (int) ($summary['online_paid_without_campaign'] ?? 0) > 0
                || (int) ($summary['deferred_invoiced_without_campaign'] ?? 0) > 0
            )
                <p class="small text-muted mb-3">
                    <i class="bi bi-question-circle"></i>
                    Bez przypisanej kampanii (tylko w statystykach kursu):
                    zamówienia {{ $formatNumber($summary['orders_created_without_campaign']) }},
                    opłaty online {{ $formatNumber($summary['online_paid_without_campaign']) }},
                    faktury odroczone {{ $formatNumber($summary['deferred_invoiced_without_campaign']) }}.
                </p>
            @endif

            {{-- Wykres trendu dziennego --}}
            @php
                $trendData = $trend ?? [];
                $courseSchedule = $course_schedule ?? [];
                $trendHasData = collect($trendData)->sum('orders_created') > 0
                    || collect($trendData)->sum('deferred_invoiced_orders') > 0
                    || collect($trendData)->sum('online_invoiced_marker_orders') > 0
                    || collect($trendData)->sum('online_paid_orders') > 0;
                $chartShow = $trendHasData || count($courseSchedule) > 0;
                $courseScheduleByDate = collect($courseSchedule)->groupBy('start_date')->sortKeys();
                $trendChart = array_map(static fn (array $r): array => [
                    'date' => $r['stat_date'],
                    'orders' => (int) $r['orders_created'],
                    'invoiced' => (int) $r['deferred_invoiced_orders'] + (int) $r['online_invoiced_marker_orders'],
                    'online_paid' => (int) $r['online_paid_orders'],
                ], $trendData);
            @endphp
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-graph-up"></i> Trend dzienny</span>
                    @if(($filters['campaign_code'] ?? null))
                        <span class="badge bg-secondary-subtle text-secondary-emphasis small">źródło: kampania {{ $filters['campaign_code'] }}</span>
                    @elseif(($filters['course_id'] ?? null))
                        <span class="badge bg-secondary-subtle text-secondary-emphasis small">źródło: kurs ID {{ $filters['course_id'] }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @unless($chartShow)
                        <p class="text-muted small mb-0">Brak danych do wykresu w wybranym zakresie.</p>
                    @else
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" id="revenueTrendShowOrders" checked>
                                <label class="form-check-label small" for="revenueTrendShowOrders">
                                    <span class="d-inline-block rounded-circle align-middle me-1" style="width:10px;height:10px;background:#0d6efd;"></span>
                                    Złożone zamówienia
                                </label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" id="revenueTrendShowInvoiced">
                                <label class="form-check-label small" for="revenueTrendShowInvoiced">
                                    <span class="d-inline-block rounded-circle align-middle me-1" style="width:10px;height:10px;background:#198754;"></span>
                                    Zaksięgowane (dodana faktura)
                                </label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" id="revenueTrendShowOnlinePaid">
                                <label class="form-check-label small" for="revenueTrendShowOnlinePaid">
                                    <span class="d-inline-block rounded-circle align-middle me-1" style="width:10px;height:10px;background:#fd7e14;"></span>
                                    Opłacone online (PayU/PayNow)
                                </label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" id="revenueTrendShowCourseSchedule" checked>
                                <label class="form-check-label small" for="revenueTrendShowCourseSchedule">
                                    <span class="d-inline-block align-middle me-1" style="width:10px;border-top:2px dashed #6f42c1;"></span>
                                    Terminy szkoleń (start)
                                </label>
                            </div>
                        </div>
                        <div style="position: relative; height: 280px;">
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                        @if(count($courseSchedule) > 0)
                            <div id="revenueCourseScheduleList" class="mt-3 border-top pt-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                    <span class="small fw-semibold text-muted">
                                        <i class="bi bi-calendar-event"></i>
                                        Terminy szkoleń w zakresie ({{ count($courseSchedule) }})
                                    </span>
                                    <span class="small text-muted">Pełne tytuły — najedź na dzień wykresu po szczegóły</span>
                                </div>
                                <div class="revenue-course-schedule-list border rounded" style="max-height: 220px; overflow-y: auto;">
                                    <ul class="list-group list-group-flush small mb-0">
                                        @foreach($courseScheduleByDate as $date => $items)
                                            @foreach($items as $item)
                                                <li class="list-group-item py-2 px-3">
                                                    <div class="d-flex flex-wrap align-items-start gap-2">
                                                        <span class="badge bg-primary-subtle text-primary-emphasis text-nowrap">{{ $date }}</span>
                                                        <span class="badge bg-light text-muted text-nowrap">{{ $item['start_time'] ?? '—' }}</span>
                                                        <span class="flex-grow-1">
                                                            @if($hasCourseLink)
                                                                <a href="{{ route('courses.show', $item['course_id']) }}" class="text-decoration-none">
                                                                    {{ $item['title'] }}
                                                                </a>
                                                            @else
                                                                {{ $item['title'] }}
                                                            @endif
                                                            <span class="text-muted">· ID {{ $item['course_id'] }}</span>
                                                        </span>
                                                    </div>
                                                </li>
                                            @endforeach
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                        <p class="small text-muted mb-0 mt-2">
                            <strong>Złożone zamówienia</strong> — wszystkie zamówienia złożone w danym dniu (<code>form_order_created</code>),
                            niezależnie od trybu płatności (odroczona lub online). To <em>nie</em> jest suma opłat PayU/PayNow i faktur — liczymy moment złożenia formularza.
                            <strong>Zaksięgowane (faktura)</strong> — faktury odroczone + znaczniki faktury online (<code>invoice_created</code>) w danym dniu.
                            <strong>Opłacone online</strong> — potwierdzone płatności PayU/PayNow (<code>payment_status_changed: paid</code>) w danym dniu.
                            Każda linia ma własną datę źródłową — wykres nie jest jednym lejkiem sprzedaży.
                            <strong>Terminy szkoleń</strong> — cienkie pionowe linie w dniu startu (<code>courses.start_date</code>); pełne tytuły w liście poniżej i w podpowiedzi po najechaniu na dzień.
                            Dane z lagiem {{ (int) ($meta['lag_days'] ?? 1) }} dni.
                            @if(($filters['campaign_code'] ?? null))
                                Wykres pokazuje tylko wybraną kampanię.
                            @endif
                        </p>
                    @endunless
                </div>
            </div>

            {{-- Tabela per kurs --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-mortarboard"></i> Rozliczenia per szkolenie</span>
                </div>
                <div class="card-body">
                    @if(empty($courses))
                        <p class="text-muted small mb-0">Brak danych w wybranym zakresie.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Kurs</th>
                                        <th class="text-end">Zamów.</th>
                                        <th class="text-end">Kwota zam.</th>
                                        <th class="text-end">Opł. online</th>
                                        <th class="text-end">Kwota online</th>
                                        <th class="text-end">Fakt. odrocz.</th>
                                        <th class="text-end">Kwota odrocz.</th>
                                        <th class="text-end">Rozlicz.</th>
                                        <th class="text-end">Kwota rozlicz.</th>
                                        <th class="text-end" title="Faktura online — znacznik, poza rozliczeniem">Fakt. online</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($courses as $course)
                                        <tr>
                                            <td>
                                                @if($hasCourseLink)
                                                    <a href="{{ route('courses.show', $course['course_id']) }}" class="text-decoration-none">
                                                        {{ $course['course_title_snapshot'] ?? ('#'.$course['course_id']) }}
                                                    </a>
                                                @else
                                                    {{ $course['course_title_snapshot'] ?? ('#'.$course['course_id']) }}
                                                @endif
                                                <div class="text-muted small">ID: {{ $course['course_id'] }}</div>
                                            </td>
                                            <td class="text-end">{{ $formatNumber($course['orders_created']) }}</td>
                                            <td class="text-end">{{ $formatMoney($course['ordered_revenue_gross']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['online_paid_orders']) }}</td>
                                            <td class="text-end">{{ $formatMoney($course['online_paid_revenue_gross']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['deferred_invoiced_orders']) }}</td>
                                            <td class="text-end">{{ $formatMoney($course['deferred_invoiced_revenue_gross']) }}</td>
                                            <td class="text-end fw-semibold">{{ $formatNumber($course['settled_orders_total']) }}</td>
                                            <td class="text-end fw-semibold">{{ $formatMoney($course['settled_revenue_gross']) }}</td>
                                            <td class="text-end text-muted">{{ $formatNumber($course['online_invoiced_marker_orders']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tabela per kampania --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-megaphone"></i> Rozliczenia per kampania</span>
                </div>
                <div class="card-body">
                    @if(empty($campaigns))
                        <p class="text-muted small mb-0">Brak danych w wybranym zakresie{{ ($filters['campaign_code'] ?? null) ? ' dla tej kampanii' : '' }}.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Kampania</th>
                                        <th class="text-end">Zamów.</th>
                                        <th class="text-end">Kwota zam.</th>
                                        <th class="text-end">Opł. online</th>
                                        <th class="text-end">Kwota online</th>
                                        <th class="text-end">Fakt. odrocz.</th>
                                        <th class="text-end">Kwota odrocz.</th>
                                        <th class="text-end">Rozlicz.</th>
                                        <th class="text-end">Kwota rozlicz.</th>
                                        <th class="text-end" title="Faktura online — znacznik, poza rozliczeniem">Fakt. online</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($campaigns as $campaign)
                                        <tr>
                                            <td>
                                                @if($hasCampaignLink && !empty($campaign['campaign_id']))
                                                    <a href="{{ route('marketing-campaigns.show', $campaign['campaign_id']) }}" class="text-decoration-none">
                                                        {{ $campaign['campaign_name'] ?? $campaign['campaign_code'] }}
                                                    </a>
                                                @else
                                                    {{ $campaign['campaign_name'] ?? $campaign['campaign_code'] }}
                                                @endif
                                                <div class="text-muted small"><code>{{ $campaign['campaign_code'] }}</code></div>
                                            </td>
                                            <td class="text-end">{{ $formatNumber($campaign['orders_created']) }}</td>
                                            <td class="text-end">{{ $formatMoney($campaign['ordered_revenue_gross']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['online_paid_orders']) }}</td>
                                            <td class="text-end">{{ $formatMoney($campaign['online_paid_revenue_gross']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['deferred_invoiced_orders']) }}</td>
                                            <td class="text-end">{{ $formatMoney($campaign['deferred_invoiced_revenue_gross']) }}</td>
                                            <td class="text-end fw-semibold">{{ $formatNumber($campaign['settled_orders_total']) }}</td>
                                            <td class="text-end fw-semibold">{{ $formatMoney($campaign['settled_revenue_gross']) }}</td>
                                            <td class="text-end text-muted">{{ $formatNumber($campaign['online_invoiced_marker_orders']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($chartShow ?? false)
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.1.0/dist/chartjs-plugin-annotation.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const canvas = document.getElementById('revenueTrendChart');
                    if (!canvas || typeof Chart === 'undefined') {
                        return;
                    }

                    const trend = @json($trendChart ?? []);
                    const courseSchedule = @json($courseSchedule ?? []);

                    const coursesByDate = courseSchedule.reduce((acc, course) => {
                        if (!acc[course.start_date]) {
                            acc[course.start_date] = [];
                        }
                        acc[course.start_date].push(course);
                        return acc;
                    }, {});

                    const buildCourseAnnotations = (courses, enabled) => {
                        if (!enabled || !courses.length) {
                            return {};
                        }

                        const annotations = {};

                        Object.keys(coursesByDate).forEach((date) => {
                            const dayCourses = coursesByDate[date];
                            const count = dayCourses.length;

                            annotations['course_date_' + date] = {
                                type: 'line',
                                xMin: date,
                                xMax: date,
                                borderColor: 'rgba(111, 66, 193, 0.45)',
                                borderWidth: 1,
                                borderDash: [5, 4],
                                label: {
                                    display: count > 1,
                                    content: String(count),
                                    position: 'start',
                                    backgroundColor: '#6f42c1',
                                    color: '#fff',
                                    font: { size: 10, weight: 'bold' },
                                    padding: { top: 2, bottom: 2, left: 4, right: 4 },
                                    borderRadius: 4,
                                    yAdjust: 6,
                                },
                            };
                        });

                        return annotations;
                    };

                    const scheduleToggle = document.getElementById('revenueTrendShowCourseSchedule');
                    const scheduleList = document.getElementById('revenueCourseScheduleList');

                    const syncCourseScheduleVisibility = () => {
                        if (scheduleList && scheduleToggle) {
                            scheduleList.classList.toggle('d-none', !scheduleToggle.checked);
                        }
                    };

                    syncCourseScheduleVisibility();

                    const chart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: trend.map(r => r.date),
                            datasets: [
                                {
                                    label: 'Złożone zamówienia',
                                    data: trend.map(r => r.orders),
                                    borderColor: '#0d6efd',
                                    backgroundColor: 'rgba(13, 110, 253, 0.12)',
                                    tension: 0.25,
                                    fill: true,
                                },
                                {
                                    label: 'Zaksięgowane (dodana faktura)',
                                    data: trend.map(r => r.invoiced),
                                    borderColor: '#198754',
                                    backgroundColor: 'rgba(25, 135, 84, 0.12)',
                                    tension: 0.25,
                                    fill: true,
                                    hidden: true,
                                },
                                {
                                    label: 'Opłacone online (PayU/PayNow)',
                                    data: trend.map(r => r.online_paid),
                                    borderColor: '#fd7e14',
                                    backgroundColor: 'rgba(253, 126, 20, 0.12)',
                                    tension: 0.25,
                                    fill: true,
                                    hidden: true,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                            },
                            plugins: {
                                legend: { display: false },
                                annotation: {
                                    annotations: buildCourseAnnotations(
                                        courseSchedule,
                                        scheduleToggle ? scheduleToggle.checked : true
                                    ),
                                },
                                tooltip: {
                                    callbacks: {
                                        afterBody: (items) => {
                                            if (!scheduleToggle?.checked || !items.length) {
                                                return [];
                                            }

                                            const date = items[0].label;
                                            const dayCourses = coursesByDate[date] || [];

                                            if (!dayCourses.length) {
                                                return [];
                                            }

                                            return [
                                                '',
                                                dayCourses.length === 1 ? 'Szkolenie:' : 'Szkolenia (' + dayCourses.length + '):',
                                                ...dayCourses.map((c) => '• ' + c.start_time + ' — ' + c.title),
                                            ];
                                        },
                                    },
                                },
                            },
                        },
                    });

                    const ordersToggle = document.getElementById('revenueTrendShowOrders');
                    const invoicedToggle = document.getElementById('revenueTrendShowInvoiced');
                    const onlinePaidToggle = document.getElementById('revenueTrendShowOnlinePaid');

                    const syncDataset = (index, checkbox) => {
                        if (!checkbox) {
                            return;
                        }
                        chart.setDatasetVisibility(index, checkbox.checked);
                        chart.update();
                    };

                    const syncCourseSchedule = () => {
                        if (!scheduleToggle || !chart.options.plugins.annotation) {
                            return;
                        }
                        chart.options.plugins.annotation.annotations = buildCourseAnnotations(
                            courseSchedule,
                            scheduleToggle.checked
                        );
                        syncCourseScheduleVisibility();
                        chart.update();
                    };

                    ordersToggle?.addEventListener('change', () => syncDataset(0, ordersToggle));
                    invoicedToggle?.addEventListener('change', () => syncDataset(1, invoicedToggle));
                    onlinePaidToggle?.addEventListener('change', () => syncDataset(2, onlinePaidToggle));
                    scheduleToggle?.addEventListener('change', syncCourseSchedule);
                });
            </script>
        @endpush
    @endif
</x-app-layout>
