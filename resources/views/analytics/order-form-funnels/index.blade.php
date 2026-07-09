<x-app-layout>
    @php
        $formatNumber = fn (int|float $value): string => number_format((float) $value, 0, ',', ' ');
        $formatRate = fn (?float $rate): string => $rate === null ? '—' : number_format($rate * 100, 2, ',', ' ').' %';
        $exportQuery = array_filter([
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'traffic_channel' => $filters['traffic_channel'] ?? null,
            'course_id' => $filters['course_id'] ?? null,
            'campaign_code' => $filters['campaign_code'] ?? null,
            'internal_promo_placement' => $filters['internal_promo_placement'] ?? null,
        ], fn ($value) => filled($value));
    @endphp

    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Analityka — Lejek formularza (kanały)</h2>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('analytics.order-form-funnels.recompute') }}" class="d-inline">
                    @csrf
                    @foreach($exportQuery as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Przelicz B4</button>
                </form>
                <a href="{{ route('analytics.order-form-funnels.export.channels', $exportQuery) }}" class="btn btn-outline-success btn-sm">CSV kanały</a>
                <a href="{{ route('analytics.order-form-funnels.export.courses', $exportQuery) }}" class="btn btn-outline-success btn-sm">CSV kursy</a>
                <a href="{{ route('analytics.order-form-funnels.export.campaigns', $exportQuery) }}" class="btn btn-outline-success btn-sm">CSV kampanie</a>
                <a href="{{ route('analytics.order-form-funnels.export.gus', $exportQuery) }}" class="btn btn-outline-success btn-sm">CSV GUS</a>
                <a href="{{ route('analytics.order-form-funnels.export.data-quality', $exportQuery) }}" class="btn btn-outline-success btn-sm">CSV jakość</a>
            </div>
        </div>
    </x-slot>

    <div class="container py-4">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="alert alert-info small">
            Raport B4 czyta wyłącznie agregaty dzienne (nie skanuje <code>analytics_events</code>).
            <code>gus_conversion_delta</code> to korelacja obserwacyjna — nie dowód przyczynowy.
            <code>internal_promo_placement</code> jest wymiarem diagnostycznym (filtr), nie głównym wykresem.
        </div>

        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><input type="text" name="traffic_channel" value="{{ $filters['traffic_channel'] }}" placeholder="traffic_channel" class="form-control form-control-sm"></div>
            <div class="col-md-2"><input type="number" name="course_id" value="{{ $filters['course_id'] }}" placeholder="course_id" class="form-control form-control-sm"></div>
            <div class="col-md-2"><input type="text" name="internal_promo_placement" value="{{ $filters['internal_promo_placement'] }}" placeholder="internal_promo_placement" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-secondary w-100">Filtruj</button></div>
        </form>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Sesje</div><div class="fs-4">{{ $formatNumber($summary['sessions_total'] ?? 0) }}</div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Zamówienia</div><div class="fs-4">{{ $formatNumber($summary['order_created'] ?? 0) }}</div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Konwersja</div><div class="fs-4">{{ $formatRate($summary['conversion_rate'] ?? null) }}</div></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">GUS success / error</div><div class="fs-6">{{ $formatNumber($summary['gus_success_sessions'] ?? 0) }} / {{ $formatNumber($summary['gus_error_sessions'] ?? 0) }}</div></div></div></div>
        </div>

        <h5 class="mb-2">Kanały ruchu</h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Data</th><th>Kanał</th><th>Raport konwersji</th><th>Sesje</th><th>Zamówienia</th><th>Konwersja</th><th>GUS ok/err</th><th>Internal promo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($channels as $row)
                        <tr>
                            <td>{{ $row->stat_date?->format('Y-m-d') }}</td>
                            <td><code>{{ $row->traffic_channel }}</code></td>
                            <td><code>{{ $row->conversion_reporting_channel }}</code></td>
                            <td>{{ $formatNumber($row->sessions_total) }}</td>
                            <td>{{ $formatNumber($row->order_created) }}</td>
                            <td>{{ $formatRate($row->conversion_rate) }}</td>
                            <td>{{ $formatNumber($row->gus_success_sessions) }} / {{ $formatNumber($row->gus_error_sessions) }}</td>
                            <td>{{ $row->internal_promo_placement ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">Brak danych — uruchom <code>analytics:aggregate-order-forms</code>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-2">Kurs × kanał</h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Data</th><th>Kurs</th><th>Kanał</th><th>Sesje</th><th>Zamówienia</th><th>Konwersja</th><th>Submit pending</th><th>Submit porzucone</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $row)
                        <tr>
                            <td>{{ $row->stat_date?->format('Y-m-d') }}</td>
                            <td>
                                <div>{{ $row->course_title_snapshot ?: 'Kurs #'.$row->course_id }}</div>
                                <small class="text-muted">#{{ $row->course_id }}</small>
                            </td>
                            <td><code>{{ $row->traffic_channel }}</code></td>
                            <td>{{ $formatNumber($row->sessions_total) }}</td>
                            <td>{{ $formatNumber($row->order_created) }}</td>
                            <td>{{ $formatRate($row->conversion_rate) }}</td>
                            <td>{{ $formatNumber($row->pending_after_submit_clicked) }}</td>
                            <td>{{ $formatNumber($row->abandoned_after_submit_clicked) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">Brak danych kurs × kanał.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-2">Kampanie</h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Data</th><th>Kampania</th><th>Kanał</th><th>Kurs</th><th>Sesje</th><th>Zamówienia</th><th>Konwersja</th><th>Bez metadanych</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $row)
                        <tr>
                            <td>{{ $row->stat_date?->format('Y-m-d') }}</td>
                            <td>
                                <div>{{ $row->campaign_name ?: ($row->campaign_code ?: '—') }}</div>
                                @if($row->campaign_code)<small class="text-muted"><code>{{ $row->campaign_code }}</code></small>@endif
                            </td>
                            <td><code>{{ $row->traffic_channel }}</code></td>
                            <td>{{ $row->course_id ?: '—' }}</td>
                            <td>{{ $formatNumber($row->sessions_total) }}</td>
                            <td>{{ $formatNumber($row->order_created) }}</td>
                            <td>{{ $formatRate($row->conversion_rate) }}</td>
                            <td>{{ $formatNumber($row->sessions_without_campaign_metadata) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">Brak danych kampanii.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-2">Jakość danych (dzienne)</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Data</th><th>Sesje</th><th>Frontend</th><th>Backend only</th><th>Atrybucja</th><th>traffic_channel</th><th>Score</th><th>Status</th><th>Flagi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data_quality as $row)
                        <tr>
                            <td>{{ $row->stat_date?->format('Y-m-d') }}</td>
                            <td>{{ $formatNumber($row->sessions_total) }}</td>
                            <td>{{ $formatNumber($row->sessions_with_frontend_events) }}</td>
                            <td>{{ $formatNumber($row->sessions_backend_only) }}</td>
                            <td>{{ $formatRate($row->attribution_coverage_rate) }}</td>
                            <td>{{ $formatRate($row->traffic_channel_coverage_rate) }}</td>
                            <td>{{ $row->tracking_data_quality_score ?? '—' }}</td>
                            <td><code>{{ $row->tracking_data_quality_status }}</code></td>
                            <td class="small text-muted">{{ is_array($row->tracking_data_quality_flags) ? implode(', ', $row->tracking_data_quality_flags) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-muted">Brak danych jakości.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
