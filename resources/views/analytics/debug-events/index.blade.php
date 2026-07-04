@php
    $displayTimezone = (string) config('analytics.debug_panel.timezone', 'Europe/Warsaw');
    $currentLayout = $layout ?? 'threads';
    $hasAdvancedFilters = filled($filters['analytics_session_id'] ?? null)
        || filled($filters['order_form_session_id'] ?? null)
        || filled($filters['course_id'] ?? null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Debug eventów analityki</h2>
            <div class="btn-group btn-group-sm" role="group" aria-label="Układ widoku">
                <a href="{{ route('analytics.debug-events.index', array_merge($filters ?? [], ['layout' => 'threads'])) }}"
                   class="btn {{ $currentLayout === 'threads' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <i class="bi bi-diagram-3"></i> Wątki sesji
                </a>
                <a href="{{ route('analytics.debug-events.index', array_merge($filters ?? [], ['layout' => 'flat'])) }}"
                   class="btn {{ $currentLayout === 'flat' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <i class="bi bi-list-ul"></i> Lista płaska
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        .debug-panel-intro {
            border-left: 4px solid var(--bs-warning);
        }
        .debug-thread-card {
            background: #fff;
            border: 1px solid var(--bs-border-color);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .debug-thread-card__header {
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--bs-border-color-translucent);
            margin-bottom: 0.75rem;
        }
        .debug-thread-card__session-badge {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .debug-thread-card__section {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--bs-border-color-translucent);
        }
        .debug-thread-card__section:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .debug-thread-card__label {
            font-size: 0.7rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--bs-secondary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .debug-entry-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem 1.25rem;
        }
        .debug-entry-grid__key {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.15rem;
        }
        .debug-entry-grid__value {
            font-size: 0.875rem;
            word-break: break-word;
        }
        .debug-journey {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem 0.5rem;
        }
        .debug-journey__step {
            background: var(--bs-primary-bg-subtle);
            color: var(--bs-primary-text-emphasis);
            border: 1px solid rgba(var(--bs-primary-rgb), 0.2);
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
        }
        .debug-journey__arrow {
            color: var(--bs-secondary-color);
            font-size: 0.75rem;
        }
        .debug-thread-card__toggle {
            font-weight: 600;
        }
        .debug-thread-card__toggle-icon {
            transition: transform 0.2s ease;
        }
        .debug-thread-card__toggle[aria-expanded="true"] .debug-thread-card__toggle-icon {
            transform: rotate(180deg);
        }
        .debug-event-timeline__item {
            display: grid;
            grid-template-columns: 4.5rem 1fr;
            gap: 0.75rem;
            padding: 0.65rem 0 0.65rem 1rem;
            border-left: 2px solid var(--bs-border-color);
            margin-left: 0.35rem;
            position: relative;
        }
        .debug-event-timeline__item::before {
            content: '';
            position: absolute;
            left: -0.4rem;
            top: 0.95rem;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            background: var(--bs-primary);
            border: 2px solid #fff;
        }
        .debug-event-timeline__item:last-child {
            padding-bottom: 0;
        }
        .debug-event-timeline__time {
            font-family: var(--bs-font-monospace);
            font-size: 0.75rem;
            color: var(--bs-secondary-color);
            padding-top: 0.15rem;
        }
        .debug-event-timeline__body {
            min-width: 0;
        }
        .debug-metadata-pre {
            font-size: 0.75rem;
            background: var(--bs-light);
            border: 1px solid var(--bs-border-color);
            border-radius: 0.5rem;
            padding: 0.75rem;
            max-height: 240px;
            overflow: auto;
        }
        .debug-dl dt {
            float: left;
            clear: left;
            width: 5.5rem;
            color: var(--bs-secondary-color);
            font-weight: 500;
        }
        .debug-dl dd {
            margin-left: 6rem;
            margin-bottom: 0.35rem;
            word-break: break-word;
        }
        .debug-flat-row td {
            vertical-align: middle;
        }
        .debug-flat-detail > td {
            border-top: 0 !important;
        }
        .debug-orphan-table th,
        .debug-orphan-table td {
            font-size: 0.875rem;
        }
    </style>

    <div class="px-3 py-3">
        <div class="container-fluid px-0">
            @include('analytics.partials.status-banner', ['showSettingsLink' => true])

            <div class="alert alert-warning debug-panel-intro small mb-3" role="alert">
                <strong>Panel techniczny</strong> — podgląd surowych zdarzeń z bazy <code>pne_analytics</code>.
                Godziny w strefie <code>{{ $displayTimezone }}</code>. Bez danych osobowych w metadata.
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-2">
                    <span class="small fw-semibold text-muted"><i class="bi bi-funnel"></i> Filtry</span>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('analytics.debug-events.index') }}">
                        <input type="hidden" name="layout" value="{{ $currentLayout }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4 col-lg-3">
                                <label for="event_name" class="form-label small mb-1">Nazwa eventu</label>
                                <input type="text" class="form-control form-control-sm" id="event_name" name="event_name" value="{{ $filters['event_name'] ?? '' }}" placeholder="np. order_form_viewed">
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label for="campaign_code" class="form-label small mb-1">Kod kampanii</label>
                                <input type="text" class="form-control form-control-sm" id="campaign_code" name="campaign_code" value="{{ $filters['campaign_code'] ?? '' }}">
                            </div>
                            <div class="col-md-2 col-lg-2">
                                <label for="date_from" class="form-label small mb-1">Data od</label>
                                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                            </div>
                            <div class="col-md-2 col-lg-2">
                                <label for="date_to" class="form-label small mb-1">Data do</label>
                                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                            </div>
                            <div class="col-md-4 col-lg-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                    <i class="bi bi-search"></i> Szukaj
                                </button>
                                <a href="{{ route('analytics.debug-events.index', ['layout' => $currentLayout]) }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                            </div>
                        </div>

                        <details class="mt-3" @if($hasAdvancedFilters) open @endif>
                            <summary class="small text-primary fw-semibold" style="cursor: pointer;">Filtry zaawansowane (ID sesji, kurs)</summary>
                            <div class="row g-3 align-items-end mt-2">
                                <div class="col-md-4">
                                    <label for="analytics_session_id" class="form-label small mb-1">Analytics session ID</label>
                                    <input type="text" class="form-control form-control-sm" id="analytics_session_id" name="analytics_session_id" value="{{ $filters['analytics_session_id'] ?? '' }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="order_form_session_id" class="form-label small mb-1">Order form session ID</label>
                                    <input type="text" class="form-control form-control-sm" id="order_form_session_id" name="order_form_session_id" value="{{ $filters['order_form_session_id'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <label for="course_id" class="form-label small mb-1">Course ID</label>
                                    <input type="number" class="form-control form-control-sm" id="course_id" name="course_id" value="{{ $filters['course_id'] ?? '' }}">
                                </div>
                            </div>
                        </details>
                    </form>
                </div>
            </div>

            @if($currentLayout === 'threads')
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-0">Wizyty użytkowników</h5>
                        <p class="text-muted small mb-0">Każda karta to jedna sesja — widać skąd wszedł i po jakich stronach przeszedł.</p>
                    </div>
                    <span class="badge text-bg-secondary fs-6">{{ $threads?->total() ?? 0 }} sesji</span>
                </div>

                @forelse($threads ?? [] as $thread)
                    @include('analytics.debug-events.partials.thread-card', [
                        'thread' => $thread,
                        'displayTimezone' => $displayTimezone,
                        'filters' => $filters,
                        'eventWarnings' => $eventWarnings,
                        'inspector' => $inspector,
                    ])
                @empty
                    <div class="card shadow-sm">
                        <div class="card-body text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                            Brak sesji dla wybranych filtrów.
                        </div>
                    </div>
                @endforelse

                @if(($threads?->hasPages()) ?? false)
                    <div class="d-flex justify-content-center mt-2">
                        {{ $threads->links() }}
                    </div>
                @endif

                @if(!empty($orphanEvents))
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <span class="fw-semibold">Zdarzenia bez przypisanej sesji</span>
                            <span class="text-muted small">— ostatnie {{ count($orphanEvents) }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 debug-orphan-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Czas</th>
                                        <th>Event</th>
                                        <th>Ścieżka</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($orphanEvents as $event)
                                        <tr>
                                            <td class="text-nowrap">{{ $event->formatUtcDatetimeLocal('occurred_at') ?? '' }}</td>
                                            <td><span class="badge text-bg-light border text-dark">{{ $event->event_name }}</span></td>
                                            <td><code class="small">{{ $event->path ?: '—' }}</code></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @else
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-0">Lista zdarzeń</h5>
                        <p class="text-muted small mb-0">Kompaktowy widok — pełne szczegóły po kliknięciu „Szczegóły”.</p>
                    </div>
                    <span class="badge text-bg-secondary fs-6">{{ $events?->total() ?? 0 }} rekordów</span>
                </div>

                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Czas <span class="fw-normal text-muted">({{ $displayTimezone }})</span></th>
                                    <th>Event</th>
                                    <th>Sesja</th>
                                    <th>Kontekst</th>
                                    <th>Ścieżka</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($events ?? [] as $event)
                                    @include('analytics.debug-events.partials.flat-event-row', [
                                        'event' => $event,
                                        'eventWarnings' => $eventWarnings,
                                        'inspector' => $inspector,
                                    ])
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            Brak eventów dla wybranych filtrów.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(($events?->hasPages()) ?? false)
                        <div class="card-footer bg-white">
                            {{ $events->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
