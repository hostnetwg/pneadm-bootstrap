<x-app-layout>
    @php
        $timezone = (string) config('analytics.sales_funnel_dashboard.timezone', 'Europe/Warsaw');
        $formatRate = function (?float $rate): string {
            if ($rate === null) {
                return '—';
            }

            return number_format($rate, 2, ',', ' ').' %';
        };
        $formatNumber = fn (int|float $value): string => number_format((float) $value, 0, ',', ' ');
        $formatMoney = fn (int|float $value): string => number_format((float) $value, 2, ',', ' ').' PLN';
        $queryBase = array_filter([
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'campaign_code' => $filters['campaign_code'] ?? null,
            'course_id' => $filters['course_id'] ?? null,
            'landing_target' => $filters['landing_target'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Analityka — Lejek sprzedaży
            </h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form id="recomputeForm" method="POST" action="{{ route('analytics.sales-funnel.recompute') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    <input type="hidden" name="campaign_code" value="{{ $filters['campaign_code'] ?? '' }}">
                    <input type="hidden" name="course_id" value="{{ $filters['course_id'] ?? '' }}">
                    <input type="hidden" name="landing_target" value="{{ $filters['landing_target'] ?? '' }}">
                    <input type="hidden" name="sort" value="{{ is_string($sort ?? null) ? $sort : '' }}">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recomputeModal">
                        <i class="bi bi-arrow-repeat"></i> Przelicz teraz
                    </button>
                </form>
                @if(config('analytics.debug_panel.enabled', false))
                    <a href="{{ route('analytics.debug-events.index') }}" class="btn btn-outline-secondary btn-sm">
                        Panel debug eventów
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid px-0">
            @include('analytics.partials.status-banner', ['showSettingsLink' => true])

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

            <div class="modal fade" id="recomputeModal" tabindex="-1" aria-labelledby="recomputeModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="recomputeModalLabel">
                                <i class="bi bi-arrow-repeat"></i> Przeliczyć agregaty?
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Przeliczę dzienne agregaty lejka dla wybranego zakresu dat:</p>
                            <p class="mb-3 fs-6">
                                <span class="badge bg-primary-subtle text-primary-emphasis">{{ $filters['date_from'] ?? '—' }}</span>
                                <span class="text-muted mx-1">→</span>
                                <span class="badge bg-primary-subtle text-primary-emphasis">{{ $filters['date_to'] ?? '—' }}</span>
                            </p>
                            <p class="text-muted small mb-2">
                                Operacja jest bezpieczna i można ją powtarzać (idempotentna).
                                Nie zmienia surowych eventów ani danych sprzedażowych — przelicza tylko podsumowania dzienne.
                            </p>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle"></i>
                                Maksymalny zakres na jedno przeliczenie:
                                <strong>{{ (int) config('analytics.sales_funnel_dashboard.recompute_max_days', 366) }}</strong> dni.
                                Dla większych zakresów użyj komendy w konsoli.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" form="recomputeForm" class="btn btn-primary btn-sm">
                                <i class="bi bi-arrow-repeat"></i> Tak, przelicz
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info small" role="alert">
                <strong>Dashboard MVP.</strong>
                Dane pochodzą wyłącznie z dziennych agregatów <code>pne_analytics</code>
                (<code>analytics_daily_course_stats</code>, <code>analytics_daily_campaign_stats</code>).
                Okres domyślny: ostatnie 14 dni (<code>{{ $timezone }}</code>).
                Widok read-only, bez danych osobowych i bez pojedynczych zamówień.
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-funnel"></i> Filtry</span>
                </div>
                <div class="card-body">
                    @php
                        $presetBase = array_filter([
                            'campaign_code' => $filters['campaign_code'] ?? null,
                            'course_id' => $filters['course_id'] ?? null,
                            'landing_target' => $filters['landing_target'] ?? null,
                            'sort' => is_string($sort ?? null) ? $sort : null,
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
                                <a href="{{ route('analytics.sales-funnel.index', array_merge($presetBase, ['date_from' => $preset['date_from'], 'date_to' => $preset['date_to']])) }}"
                                   class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    {{ $preset['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <form method="GET" action="{{ route('analytics.sales-funnel.index') }}" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label for="date_from" class="form-label small">Od</label>
                            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label small">Do</label>
                            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label for="campaign_code" class="form-label small">Campaign code</label>
                            <input type="text" class="form-control form-control-sm" id="campaign_code" name="campaign_code" value="{{ $filters['campaign_code'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label for="course_id" class="form-label small">Course ID</label>
                            <input type="number" class="form-control form-control-sm" id="course_id" name="course_id" value="{{ $filters['course_id'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label for="landing_target" class="form-label small">Landing target</label>
                            <select class="form-select form-select-sm" id="landing_target" name="landing_target">
                                <option value="">— wszystkie —</option>
                                <option value="course_description" @selected(($filters['landing_target'] ?? '') === 'course_description')>course_description</option>
                                <option value="order_form_direct" @selected(($filters['landing_target'] ?? '') === 'order_form_direct')>order_form_direct</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="bi bi-search"></i> Filtruj
                            </button>
                            <a href="{{ route('analytics.sales-funnel.index') }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                        </div>
                    </form>
                    @unless($has_utm_filters)
                        <p class="small text-muted mb-0 mt-2">
                            Filtry UTM nie są dostępne — kolumny UTM nie występują w tabelach dziennych agregatów Etapu 1C.
                        </p>
                    @endunless
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
            <div class="row g-3 mb-3">
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Kliknięcia linków</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['short_link_visits']) }}</div>
                        @if(!empty($comparison))
                            @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'short_link_visits'])
                        @endif
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Wejścia w opis</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['description_views']) }}</div>
                        @if(!empty($comparison))
                            @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'description_views'])
                        @endif
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Wejścia w formularz</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['form_views']) }}</div>
                        @if(!empty($comparison))
                            @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'form_views'])
                        @endif
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Próby submitu</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['form_submits']) }}</div>
                        @if(!empty($comparison))
                            @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'form_submits'])
                        @endif
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Błędy walidacji</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['validation_errors']) }}</div>
                        @if(!empty($comparison))
                            @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'validation_errors'])
                        @endif
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Zamówienia</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['orders_created']) }}</div>
                        @if(!empty($comparison))
                            @include('analytics.partials.period-delta', ['comparison' => $comparison, 'metricKey' => 'orders_created'])
                        @endif
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Przychód brutto</div>
                        <div class="fs-5 fw-semibold">{{ $formatMoney($summary['revenue_gross']) }}</div>
                    </div></div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted">Wskaźniki konwersji</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 small">
                        <div class="col-md-4">Opis → formularz: <strong>{{ $formatRate($rates['description_to_form']) }}</strong></div>
                        <div class="col-md-4">Formularz → submit: <strong>{{ $formatRate($rates['form_to_submit']) }}</strong></div>
                        <div class="col-md-4">Submit → zamówienie: <strong>{{ $formatRate($rates['submit_to_order']) }}</strong></div>
                        <div class="col-md-4">Formularz → zamówienie: <strong>{{ $formatRate($rates['form_to_order']) }}</strong></div>
                        <div class="col-md-4">Błędy walidacji / submit: <strong>{{ $formatRate($rates['validation_errors_per_submit']) }}</strong></div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted">Lejek ogólny</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Etap</th>
                                    <th class="text-end">Liczba</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($funnel as $step)
                                    <tr>
                                        <td>{{ $step['label'] }}</td>
                                        <td class="text-end">{{ $formatNumber($step['value']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($alerts !== [])
                <div class="card shadow-sm mb-3 border-warning">
                    <div class="card-header bg-white py-2">
                        <span class="small fw-semibold text-muted">Proste ostrzeżenia</span>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0 small">
                            @foreach($alerts as $alert)
                                <li class="text-{{ $alert['type'] === 'danger' ? 'danger' : 'warning' }}">{{ $alert['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                    <span class="small fw-semibold text-muted">Kampanie</span>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach([
                            'orders_created' => 'Zamówienia',
                            'order_form_views' => 'Formularze',
                            'form_to_order_rate' => 'Konwersja',
                            'validation_failures' => 'Błędy walidacji',
                        ] as $sortKey => $sortLabel)
                            <a href="{{ route('analytics.sales-funnel.index', array_merge($queryBase, ['sort' => $sortKey])) }}"
                               class="btn btn-outline-secondary btn-sm {{ $sort === $sortKey ? 'active' : '' }}">
                                {{ $sortLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Campaign code</th>
                                    <th>Channel</th>
                                    <th>Landing</th>
                                    <th class="text-end">Linki</th>
                                    <th class="text-end">Opis</th>
                                    <th class="text-end">Formularz</th>
                                    <th class="text-end">Submit</th>
                                    <th class="text-end">Błędy</th>
                                    <th class="text-end">Zamów.</th>
                                    <th class="text-end">Przychód</th>
                                    <th class="text-end">Form→Zam.</th>
                                    <th class="text-end">Submit→Zam.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campaigns as $row)
                                    <tr>
                                        <td>
                                            @if(!empty($row['campaign_id']))
                                                <a href="{{ route('marketing-campaigns.show', $row['campaign_id']) }}" target="_blank" rel="noopener" title="Otwórz kartę kampanii">
                                                    <code>{{ $row['campaign_code'] }}</code>
                                                </a>
                                            @else
                                                <code>{{ $row['campaign_code'] }}</code>
                                            @endif
                                            @if(!empty($row['campaign_name']))
                                                <div class="small text-muted">{{ $row['campaign_name'] }}</div>
                                            @endif
                                            @if(!empty($row['campaign_course_title']))
                                                <div class="small text-muted">
                                                    <i class="bi bi-mortarboard"></i>
                                                    @if(!empty($row['campaign_course_id']))
                                                        <a href="{{ route('courses.show', $row['campaign_course_id']) }}" target="_blank" rel="noopener" class="text-muted">{{ $row['campaign_course_title'] }}</a>
                                                    @else
                                                        {{ $row['campaign_course_title'] }}
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ $row['campaign_channel'] ?? '—' }}</td>
                                        <td>{{ $row['landing_target'] ?? '—' }}</td>
                                        <td class="text-end">{{ $formatNumber($row['link_entries']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['description_views']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['form_views']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['form_submits']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['validation_errors']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['orders_created']) }}</td>
                                        <td class="text-end">{{ $formatMoney($row['revenue_gross']) }}</td>
                                        <td class="text-end">{{ $formatRate($row['form_to_order_rate']) }}</td>
                                        <td class="text-end">{{ $formatRate($row['submit_to_order_rate']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="12" class="text-center text-muted py-3">Brak danych kampanii w wybranym okresie.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted">Szkolenia</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Course ID</th>
                                    <th>Tytuł</th>
                                    <th class="text-end">Opis</th>
                                    <th class="text-end">Formularz</th>
                                    <th class="text-end">Submit</th>
                                    <th class="text-end">Błędy</th>
                                    <th class="text-end">Zamów.</th>
                                    <th class="text-end">Przychód</th>
                                    <th class="text-end">Opis→Form.</th>
                                    <th class="text-end">Form→Zam.</th>
                                    <th class="text-end">Submit→Zam.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($courses as $row)
                                    <tr>
                                        <td>
                                            <a href="{{ route('courses.show', $row['course_id']) }}" target="_blank" rel="noopener" title="Otwórz kartę szkolenia">
                                                {{ $row['course_id'] }}
                                            </a>
                                        </td>
                                        <td>
                                            @if(!empty($row['course_title_snapshot']))
                                                <a href="{{ route('courses.show', $row['course_id']) }}" target="_blank" rel="noopener" title="Otwórz kartę szkolenia">
                                                    {{ $row['course_title_snapshot'] }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $formatNumber($row['description_views']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['form_views']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['form_submits']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['validation_errors']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['orders_created']) }}</td>
                                        <td class="text-end">{{ $formatMoney($row['revenue_gross']) }}</td>
                                        <td class="text-end">{{ $formatRate($row['description_to_form_rate']) }}</td>
                                        <td class="text-end">{{ $formatRate($row['form_to_order_rate']) }}</td>
                                        <td class="text-end">{{ $formatRate($row['submit_to_order_rate']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="11" class="text-center text-muted py-3">Brak danych szkoleń w wybranym okresie.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted">Landing target</span>
                </div>
                <div class="card-body">
                    @if(($landing_targets['by_landing_target'] ?? []) === [])
                        <p class="small text-muted">
                            Brak wierszy agregatów kampanii z wypełnionym <code>landing_target</code>
                            (rollup Etapu 1C zapisuje <code>NULL</code>).
                            Poniżej porównanie proxy z agregatów kursów.
                        </p>
                    @else
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Landing target</th>
                                        <th class="text-end">Formularz</th>
                                        <th class="text-end">Submit</th>
                                        <th class="text-end">Zamów.</th>
                                        <th class="text-end">Form→Zam.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($landing_targets['by_landing_target'] as $row)
                                        <tr>
                                            <td><code>{{ $row['landing_target'] }}</code></td>
                                            <td class="text-end">{{ $formatNumber($row['form_views']) }}</td>
                                            <td class="text-end">{{ $formatNumber($row['form_submits']) }}</td>
                                            <td class="text-end">{{ $formatNumber($row['orders_created']) }}</td>
                                            <td class="text-end">{{ $formatRate($row['form_to_order_rate']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Ścieżka (proxy)</th>
                                    <th class="text-end">Formularz / opis</th>
                                    <th class="text-end">Submit</th>
                                    <th class="text-end">Zamów.</th>
                                    <th class="text-end">Konwersja</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($landing_targets['course_proxy'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ $formatNumber($row['form_views']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['form_submits']) }}</td>
                                        <td class="text-end">{{ $formatNumber($row['orders_created']) }}</td>
                                        <td class="text-end">{{ $formatRate($row['form_to_order_rate']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
