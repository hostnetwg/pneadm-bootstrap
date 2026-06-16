<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Marketing → Lejek konwersji</h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('marketing-funnel.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Data od</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $from->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data do</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $to->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Szkolenie</label>
                            <select name="course_id" class="form-select">
                                <option value="">Wszystkie (top 100)</option>
                                @foreach($allCourses as $c)
                                    <option value="{{ $c->id }}" @selected(request('course_id') == $c->id)>
                                        #{{ $c->id }} — {{ Str::limit($c->title, 60) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Typ źródła</label>
                            <select name="source_type_id" class="form-select" title="Filtruje tylko tabelę kampanii po prawej">
                                <option value="">Wszystkie</option>
                                @foreach($sourceTypes as $st)
                                    <option value="{{ $st->id }}" @selected($sourceTypeId == $st->id)>{{ $st->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Tylko panel kampanii →</div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtruj</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-light border small mb-4 py-2">
                <i class="bi bi-info-circle text-muted"></i>
                <strong>Okres:</strong> {{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}.
                <strong>Lejek per szkolenie</strong> — wejścia i wszystkie zamówienia kursu w okresie (także bez kampanii).
                <strong>Kampanie</strong> (prawa tabela) — tylko zamówienia z <code>fb_source</code> w okresie; filtr typu źródła dotyczy wyłącznie tej tabeli.
                Listy <a href="{{ route('marketing-campaigns.index') }}">kampanii</a> i <a href="{{ route('marketing-source-types.index') }}">typów źródeł</a> — liczniki za <strong>całą historię</strong>.
                <span class="d-block mt-1">Wejścia: max 1×/gość/kurs/dzień (<code>course_page_stats_daily</code>). Formularz = <code>order-form</code> + <code>deferred-order</code>.</span>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-primary h-100">
                        <div class="card-body text-center">
                            <div class="text-muted small">Wejścia na opis</div>
                            <div class="fs-3 fw-bold">{{ number_format($totals['views_course_show'], 0, ',', ' ') }}</div>
                            <div class="small text-muted mt-1">unikalne / dzień</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info h-100">
                        <div class="card-body text-center">
                            <div class="text-muted small">Wejścia na formularz</div>
                            <div class="fs-3 fw-bold">{{ number_format($totals['views_order_form'], 0, ',', ' ') }}</div>
                            <div class="small text-muted mt-1">order-form + deferred-order</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning h-100">
                        <div class="card-body text-center">
                            <div class="text-muted small">Zamówienia złożone</div>
                            <div class="fs-3 fw-bold">{{ number_format($totals['orders_submitted'], 0, ',', ' ') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success h-100">
                        <div class="card-body text-center">
                            <div class="text-muted small">Z fakturą (invoice_number)</div>
                            <div class="fs-3 fw-bold">{{ number_format($totals['orders_invoiced'] ?? $totals['orders_paid'], 0, ',', ' ') }}</div>
                            <div class="small text-muted mt-1">Wystawiona faktura (invoice_number)</div>
                            <div class="small text-muted mt-1">Bez kampanii: {{ number_format($ordersWithoutCampaign, 0, ',', ' ') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header"><strong>Lejek per szkolenie</strong></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Szkolenie</th>
                                            <th class="text-end" title="Unikalne wejścia/dzień na /courses/{id}">Opis</th>
                                            <th class="text-end" title="Unikalne wejścia/dzień: order-form + deferred-order">Formularz</th>
                                            <th class="text-end">Złożone</th>
                                            <th class="text-end">Z fakturą</th>
                                            <th class="text-end">CR opis→fakt.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($courses as $course)
                                            @php $row = $funnelByCourse[$course->id] ?? null; @endphp
                                            <tr>
                                                <td>{{ $course->id }}</td>
                                                <td>
                                                    <div class="fw-semibold">{{ Str::limit($course->title, 70) }}</div>
                                                    <small class="text-muted">{{ $course->start_date?->format('d.m.Y') }}</small>
                                                </td>
                                                <td class="text-end">{{ number_format($row['views_course_show'] ?? 0, 0, ',', ' ') }}</td>
                                                <td class="text-end">{{ number_format($row['views_order_form'] ?? 0, 0, ',', ' ') }}</td>
                                                <td class="text-end">{{ number_format($row['orders_submitted'] ?? 0, 0, ',', ' ') }}</td>
                                                <td class="text-end">{{ number_format($row['orders_invoiced'] ?? $row['orders_paid'] ?? 0, 0, ',', ' ') }}</td>
                                                <td class="text-end">{{ $statsService->formatCr($row['cr_show_to_invoiced'] ?? null) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-center text-muted py-4">Brak szkoleń</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <strong>Kampanie (zamówienia w okresie {{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }})</strong>
                            @if($sourceTypeId)
                                <a href="{{ route('marketing-campaigns.index', ['source_type_id' => $sourceTypeId]) }}" class="btn btn-sm btn-outline-primary">
                                    Lista kampanii tego typu
                                </a>
                            @endif
                        </div>
                        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Kod</th>
                                        <th>Kampania</th>
                                        <th class="text-end">Złoż.</th>
                                        <th class="text-end">Fakt.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($campaignRows as $campaign)
                                        <tr>
                                            <td><code>{{ $campaign->campaign_code }}</code></td>
                                            <td>
                                                @if($campaign->source_type_name)
                                                    <span class="badge" style="background: {{ $campaign->source_type_color ?? '#6c757d' }}">{{ $campaign->source_type_name }}</span>
                                                @endif
                                                <div class="small">{{ Str::limit($campaign->name, 50) }}</div>
                                            </td>
                                            <td class="text-end">{{ (int) $campaign->orders_submitted }}</td>
                                            <td class="text-end">{{ (int) $campaign->orders_paid }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-3">Brak danych</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
