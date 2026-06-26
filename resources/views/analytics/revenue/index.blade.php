<x-app-layout>
    @php
        $timezone = $meta['timezone'] ?? 'Europe/Warsaw';
        $formatNumber = fn (int|float $value): string => number_format((float) $value, 0, ',', ' ');
        $formatMoney = fn (int|float $value): string => number_format((float) $value, 2, ',', ' ').' PLN';
        $hasCourseLink = \Illuminate\Support\Facades\Route::has('courses.show');
        $hasCampaignLink = \Illuminate\Support\Facades\Route::has('marketing-campaigns.show');
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
</x-app-layout>
