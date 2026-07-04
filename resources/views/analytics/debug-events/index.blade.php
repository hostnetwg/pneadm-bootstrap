<x-app-layout>
    @php
        $displayTimezone = (string) config('analytics.debug_panel.timezone', 'Europe/Warsaw');
    @endphp

    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Analityka — Debug eventów
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid px-0">
            @include('analytics.partials.status-banner', ['showSettingsLink' => true])

            <div class="alert alert-warning small" role="alert">
                <strong>Panel techniczny.</strong>
                Ten widok służy wyłącznie do diagnostyki zapisu eventów w bazie <code>pne_analytics</code>.
                To nie jest dashboard biznesowy. Dane są tylko do odczytu.
                Godziny w tabeli są wyświetlane w strefie <code>{{ $displayTimezone }}</code>.
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-funnel"></i> Filtry</span>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('analytics.debug-events.index') }}" class="row g-2 align-items-end">
                        <div class="col-md-3 col-xl-2">
                            <label for="event_name" class="form-label small">Event</label>
                            <input type="text" class="form-control form-control-sm" id="event_name" name="event_name" value="{{ $filters['event_name'] ?? '' }}" placeholder="np. order_form_viewed">
                        </div>
                        <div class="col-md-3 col-xl-2">
                            <label for="campaign_code" class="form-label small">Campaign code</label>
                            <input type="text" class="form-control form-control-sm" id="campaign_code" name="campaign_code" value="{{ $filters['campaign_code'] ?? '' }}">
                        </div>
                        <div class="col-md-2 col-xl-1">
                            <label for="course_id" class="form-label small">Course ID</label>
                            <input type="number" class="form-control form-control-sm" id="course_id" name="course_id" value="{{ $filters['course_id'] ?? '' }}">
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <label for="analytics_session_id" class="form-label small">Analytics session ID</label>
                            <input type="text" class="form-control form-control-sm" id="analytics_session_id" name="analytics_session_id" value="{{ $filters['analytics_session_id'] ?? '' }}">
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <label for="order_form_session_id" class="form-label small">Order form session ID</label>
                            <input type="text" class="form-control form-control-sm" id="order_form_session_id" name="order_form_session_id" value="{{ $filters['order_form_session_id'] ?? '' }}">
                        </div>
                        <div class="col-md-2 col-xl-1">
                            <label for="date_from" class="form-label small">Od</label>
                            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-2 col-xl-1">
                            <label for="date_to" class="form-label small">Do</label>
                            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-4 col-xl-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="bi bi-search"></i> Filtruj
                            </button>
                            <a href="{{ route('analytics.debug-events.index') }}" class="btn btn-outline-secondary btn-sm">
                                Wyczyść
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="fw-semibold">Ostatnie eventy</span>
                        <span class="text-muted small">domyślnie 100 rekordów na stronę</span>
                    </div>
                    <span class="badge text-bg-secondary">{{ $events->total() }} rekordów</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>occurred_at<br><span class="small text-muted">{{ $displayTimezone }}</span></th>
                                <th>event_name</th>
                                <th>event_category</th>
                                <th>analytics_session_id</th>
                                <th>order_form_session_id</th>
                                <th>course_id</th>
                                <th>course_title_snapshot</th>
                                <th>campaign_code</th>
                                <th>landing_target</th>
                                <th>utm_source</th>
                                <th>utm_medium</th>
                                <th>utm_campaign</th>
                                <th>route_name</th>
                                <th>path</th>
                                <th>referrer_domain</th>
                                <th>device_type</th>
                                <th>created_at<br><span class="small text-muted">{{ $displayTimezone }}</span></th>
                                <th>metadata_json</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($events as $event)
                                @php
                                    $warnings = $eventWarnings[$event->id] ?? [];
                                    $metadata = is_array($event->metadata) ? $event->metadata : [];
                                    $safeMetadata = $inspector->redacted($metadata);
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ $event->formatUtcDatetimeLocal('occurred_at') ?? '' }}</td>
                                    <td>
                                        <code>{{ $event->event_name }}</code>
                                        @if($warnings !== [])
                                            <div class="badge text-bg-danger mt-1">PII warning</div>
                                        @endif
                                    </td>
                                    <td>{{ $event->event_category }}</td>
                                    <td><code class="small">{{ $event->analytics_session_id }}</code></td>
                                    <td><code class="small">{{ $event->order_form_session_id }}</code></td>
                                    <td>{{ $event->course_id }}</td>
                                    <td class="text-break">{{ $event->course_title_snapshot }}</td>
                                    <td><code>{{ $event->campaign_code }}</code></td>
                                    <td>{{ $event->landing_target }}</td>
                                    <td>{{ $event->utm_source }}</td>
                                    <td>{{ $event->utm_medium }}</td>
                                    <td>{{ $event->utm_campaign }}</td>
                                    <td>{{ $event->route_name }}</td>
                                    <td><code class="small">{{ $event->path }}</code></td>
                                    <td>{{ $event->referrer_domain }}</td>
                                    <td>{{ $event->device_type }}</td>
                                    <td class="text-nowrap">{{ $event->formatUtcDatetimeLocal('created_at') ?? '' }}</td>
                                    <td style="min-width: 260px;">
                                        @if($warnings !== [])
                                            <div class="alert alert-danger py-1 px-2 mb-2 small">
                                                Uwaga: wykryto niedozwolony klucz w metadata_json:
                                                <code>{{ implode(', ', $warnings) }}</code>
                                            </div>
                                        @endif

                                        @if($safeMetadata !== [])
                                            <details>
                                                <summary class="small text-primary" style="cursor: pointer;">Pokaż metadata_json</summary>
                                                <pre class="small bg-light border rounded p-2 mt-2 mb-0">{{ json_encode($safeMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </details>
                                        @else
                                            <span class="text-muted small">Brak</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="18" class="text-center text-muted py-4">
                                        Brak eventów dla wybranych filtrów.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($events->hasPages())
                    <div class="card-footer bg-white">
                        {{ $events->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
