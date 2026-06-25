<x-app-layout>
    @php
        $timezone = $meta['timezone'] ?? 'Europe/Warsaw';
        $formatNumber = fn (int|float $value): string => number_format((float) $value, 0, ',', ' ');
        $formatRate = function (?float $rate): string {
            if ($rate === null) {
                return '—';
            }

            return number_format($rate, 2, ',', ' ').' %';
        };
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
                Analityka — Porzucenia formularza
            </h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('analytics.form-abandonments.export.courses', $exportQuery) }}" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv"></i> Eksport CSV — kursy
                </a>
                <a href="{{ route('analytics.form-abandonments.export.campaigns', $exportQuery) }}" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv"></i> Eksport CSV — kampanie
                </a>
                @if(\Illuminate\Support\Facades\Route::has('analytics.sales-funnel.index'))
                    <a href="{{ route('analytics.sales-funnel.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-funnel"></i> Lejek sprzedaży
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid px-0">
            @includeIf('analytics.partials.status-banner', ['showSettingsLink' => true])

            <div class="alert alert-info small" role="alert">
                <strong>Dashboard MVP (read-only).</strong>
                Dane pochodzą wyłącznie z dziennych agregatów porzuceń <code>pne_analytics</code>
                (<code>analytics_daily_form_abandonment_stats</code>, <code>analytics_daily_campaign_abandonment_stats</code>).
                Dane są liczone dla <strong>sesji rozpoczętych</strong> w wybranym dniu / zakresie.
                Agregacja ma <strong>lag {{ (int) ($meta['lag_days'] ?? 2) }} dni</strong> (<code>{{ $timezone }}</code>).
                Bez danych osobowych i bez wartości pól formularza.
            </div>

            {{-- Filtry --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-funnel"></i> Filtry</span>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('analytics.form-abandonments.index') }}" class="row g-2 align-items-end">
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
                            <a href="{{ route('analytics.form-abandonments.index') }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                        </div>
                    </form>
                    <p class="small text-muted mb-0 mt-2">
                        Domyślny zakres: ostatnie {{ (int) ($meta['default_days'] ?? 14) }} dni zakończone na dniu dojrzałym
                        (dziś − {{ (int) ($meta['lag_days'] ?? 2) }}). Maksymalny zakres: {{ (int) ($meta['max_days'] ?? 366) }} dni.
                    </p>
                    <p class="small text-muted mb-0 mt-1">
                        <i class="bi bi-filetype-csv"></i>
                        Eksport CSV zawiera wyłącznie agregaty bez danych osobowych i bez wartości pól formularza.
                        Przyciski eksportu (u góry) zachowują aktualne filtry.
                    </p>
                </div>
            </div>

            {{-- Kafelki podsumowania --}}
            <div class="row g-3 mb-3">
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Sesje formularza</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['sessions_total']) }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Rozpoczęto formularz</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['reached_started']) }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Kliknięto submit</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['reached_submit_clicked']) }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Podjęto próbę submitu</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['reached_submit_attempted']) }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Utworzono zamówienie</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['reached_created']) }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Porzucono łącznie</div>
                        <div class="fs-4 fw-semibold">{{ $formatNumber($summary['abandoned_total']) }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted">Konwersja do zamówienia</div>
                        <div class="fs-4 fw-semibold">{{ $formatRate($summary['conversion_rate']) }}</div>
                    </div></div>
                </div>
            </div>

            {{-- Kubełki: gdzie odpadają --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-bar-chart-steps"></i> Gdzie odpadają użytkownicy</span>
                </div>
                <div class="card-body">
                    @if((int) $summary['sessions_total'] === 0)
                        <p class="text-muted small mb-0">Brak danych w wybranym zakresie.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Etap</th>
                                        <th class="text-end" style="width: 110px;">Sesje</th>
                                        <th class="text-end" style="width: 90px;">% sesji</th>
                                        <th style="width: 40%;">Udział</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($buckets as $bucket)
                                        <tr>
                                            <td>{{ $bucket['label'] }}</td>
                                            <td class="text-end">{{ $formatNumber($bucket['count']) }}</td>
                                            <td class="text-end">{{ $formatRate($bucket['percent']) }}</td>
                                            <td>
                                                <div class="progress" style="height: 14px;" role="progressbar"
                                                     aria-valuenow="{{ (int) ($bucket['percent'] ?? 0) }}" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar {{ $bucket['key'] === 'converted' ? 'bg-success' : 'bg-warning' }}"
                                                         style="width: {{ $bucket['percent'] ?? 0 }}%;"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-muted mb-0 mt-2">
                            Kubełki są rozłączne i sumują się do liczby sesji ({{ $formatNumber($summary['sessions_total']) }}).
                        </p>
                    @endif
                </div>
            </div>

            {{-- Tabela per kurs --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-mortarboard"></i> Porzucenia per szkolenie</span>
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
                                        <th class="text-end">Sesje</th>
                                        <th class="text-end">Start</th>
                                        <th class="text-end">Klik submit</th>
                                        <th class="text-end">Próba</th>
                                        <th class="text-end">Zamówienie</th>
                                        <th class="text-end">Przed startem</th>
                                        <th class="text-end">Po starcie</th>
                                        <th class="text-end">Po kliknięciu</th>
                                        <th class="text-end">Po próbie</th>
                                        <th class="text-end">Konwersja</th>
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
                                            <td class="text-end fw-semibold">{{ $formatNumber($course['sessions_total']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['reached_started']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['reached_submit_clicked']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['reached_submit_attempted']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['reached_created']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['viewed_not_started']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['started_not_submit_clicked']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['submit_clicked_not_attempted']) }}</td>
                                            <td class="text-end">{{ $formatNumber($course['submit_attempted_not_created']) }}</td>
                                            <td class="text-end">{{ $formatRate($course['conversion_rate']) }}</td>
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
                    <span class="small fw-semibold text-muted"><i class="bi bi-megaphone"></i> Porzucenia per kampania</span>
                </div>
                <div class="card-body">
                    @if(empty($campaigns))
                        <p class="text-muted small mb-0">Brak danych kampanii w wybranym zakresie.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Kampania</th>
                                        <th class="text-end">Sesje</th>
                                        <th class="text-end">Start</th>
                                        <th class="text-end">Klik submit</th>
                                        <th class="text-end">Próba</th>
                                        <th class="text-end">Zamówienie</th>
                                        <th class="text-end">Przed startem</th>
                                        <th class="text-end">Po starcie</th>
                                        <th class="text-end">Po kliknięciu</th>
                                        <th class="text-end">Po próbie</th>
                                        <th class="text-end">Konwersja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($campaigns as $campaign)
                                        <tr>
                                            <td>
                                                @if($hasCampaignLink && ! empty($campaign['campaign_id']))
                                                    <a href="{{ route('marketing-campaigns.show', $campaign['campaign_id']) }}" class="text-decoration-none">
                                                        {{ $campaign['campaign_code'] }}
                                                    </a>
                                                @else
                                                    {{ $campaign['campaign_code'] }}
                                                @endif
                                                @if(! empty($campaign['campaign_name']))
                                                    <div class="text-muted small">{{ $campaign['campaign_name'] }}</div>
                                                @endif
                                            </td>
                                            <td class="text-end fw-semibold">{{ $formatNumber($campaign['sessions_total']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['reached_started']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['reached_submit_clicked']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['reached_submit_attempted']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['reached_created']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['viewed_not_started']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['started_not_submit_clicked']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['submit_clicked_not_attempted']) }}</td>
                                            <td class="text-end">{{ $formatNumber($campaign['submit_attempted_not_created']) }}</td>
                                            <td class="text-end">{{ $formatRate($campaign['conversion_rate']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Jak czytać dane --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-info-circle"></i> Jak czytać dane</span>
                </div>
                <div class="card-body">
                    <ol class="small text-muted mb-0 ps-3">
                        <li>Dane pokazują sesje <strong>rozpoczęte</strong> w wybranym dniu lub zakresie (atrybucja do dnia pierwszego eventu).</li>
                        <li>Porzucenia są liczone po czasie, z opóźnieniem <strong>lag = {{ (int) ($meta['lag_days'] ?? 2) }} dni</strong> — najnowsze dni mogą jeszcze nie być policzone.</li>
                        <li>Jeżeli użytkownik ma zablokowany JS albo działa tryb <code>aggregate_only</code>/<code>off</code>, część eventów JS (start, klik submit) może nie powstać.</li>
                        <li>Sesje sprzed wdrożenia trackingu JS (B2) częściej wpadają do „Weszli, ale nie zaczęli formularza”.</li>
                        <li>Dane nie zawierają danych osobowych ani wartości pól formularza — to wyłącznie zliczenia.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
