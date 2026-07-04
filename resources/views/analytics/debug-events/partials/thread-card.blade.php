@php
    $entry = $thread['entry'] ?? [];
    $sessionId = $thread['analytics_session_id'] ?? '';
    $collapseId = 'debugThread'.md5($sessionId);
    $firstAt = filled($thread['first_occurred_at'] ?? null)
        ? \Carbon\Carbon::parse($thread['first_occurred_at'])->timezone($displayTimezone)
        : null;
    $lastAt = filled($thread['last_occurred_at'] ?? null)
        ? \Carbon\Carbon::parse($thread['last_occurred_at'])->timezone($displayTimezone)
        : null;
    $durationSeconds = ($firstAt && $lastAt) ? max(0, $firstAt->diffInSeconds($lastAt)) : null;

    $eventBadgeClass = static function (string $eventName): string {
        if (str_starts_with($eventName, 'form_order')) {
            return 'text-bg-success';
        }
        if (str_starts_with($eventName, 'order_form')) {
            return 'text-bg-primary';
        }
        if (str_starts_with($eventName, 'campaign')) {
            return 'text-bg-info';
        }
        if (str_starts_with($eventName, 'course')) {
            return 'text-bg-secondary';
        }

        return 'text-bg-light border text-dark';
    };
@endphp

<article class="debug-thread-card">
    <header class="debug-thread-card__header">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="debug-thread-card__session-badge">
                        <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                        Sesja <code>{{ $thread['session_short'] ?? '—' }}</code>
                    </span>
                    <span class="badge rounded-pill text-bg-dark">{{ $thread['event_count'] ?? 0 }} zdarzeń</span>
                    @if($durationSeconds !== null && $durationSeconds > 0)
                        <span class="badge rounded-pill text-bg-light border text-muted">{{ $durationSeconds }} s w sesji</span>
                    @endif
                </div>
                @if($firstAt)
                    <div class="small text-muted">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        {{ $firstAt->format('Y-m-d H:i:s') }}
                        @if($lastAt && $lastAt->ne($firstAt))
                            <span class="mx-1">→</span>{{ $lastAt->format('H:i:s') }}
                        @endif
                        <span class="ms-1">({{ $displayTimezone }})</span>
                    </div>
                @endif
            </div>
            <a href="{{ route('analytics.debug-events.index', array_merge($filters ?? [], ['analytics_session_id' => $sessionId, 'layout' => 'threads'])) }}"
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-funnel"></i> Tylko ta sesja
            </a>
        </div>
    </header>

    @if(filled($entry['referrer_domain'] ?? null) || filled($entry['campaign_code'] ?? null) || filled($entry['path'] ?? null) || filled($entry['utm_source'] ?? null))
        <section class="debug-thread-card__section">
            <h6 class="debug-thread-card__label">Skąd wszedł</h6>
            <div class="debug-entry-grid">
                @if(filled($entry['referrer_domain'] ?? null))
                    <div class="debug-entry-grid__item">
                        <span class="debug-entry-grid__key">Referrer</span>
                        <span class="debug-entry-grid__value">{{ $entry['referrer_domain'] }}</span>
                    </div>
                @endif
                @if(filled($entry['campaign_code'] ?? null))
                    <div class="debug-entry-grid__item">
                        <span class="debug-entry-grid__key">Kampania</span>
                        <span class="debug-entry-grid__value"><code>{{ $entry['campaign_code'] }}</code></span>
                    </div>
                @endif
                @if(filled($entry['utm_source'] ?? null))
                    <div class="debug-entry-grid__item">
                        <span class="debug-entry-grid__key">UTM</span>
                        <span class="debug-entry-grid__value">
                            {{ $entry['utm_source'] }}
                            @if(filled($entry['utm_medium'] ?? null))/ {{ $entry['utm_medium'] }}@endif
                            @if(filled($entry['utm_campaign'] ?? null)) · {{ $entry['utm_campaign'] }}@endif
                        </span>
                    </div>
                @endif
                @if(filled($entry['path'] ?? null))
                    <div class="debug-entry-grid__item">
                        <span class="debug-entry-grid__key">Pierwsza strona</span>
                        <span class="debug-entry-grid__value"><code>{{ $entry['path'] }}</code></span>
                    </div>
                @endif
                @if(filled($entry['page_label'] ?? null))
                    <div class="debug-entry-grid__item">
                        <span class="debug-entry-grid__key">Etykieta</span>
                        <span class="debug-entry-grid__value">{{ $entry['page_label'] }}</span>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if(!empty($thread['journey_steps']))
        <section class="debug-thread-card__section">
            <h6 class="debug-thread-card__label">Ścieżka w sesji</h6>
            <div class="debug-journey" aria-label="Ścieżka nawigacji użytkownika">
                @foreach($thread['journey_steps'] as $index => $step)
                    @if($index > 0)
                        <span class="debug-journey__arrow" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
                    @endif
                    <span class="debug-journey__step">{{ $step['label'] ?? '—' }}</span>
                @endforeach
            </div>
        </section>
    @endif

    <section class="debug-thread-card__section debug-thread-card__section--events">
        <button class="debug-thread-card__toggle btn btn-link btn-sm text-decoration-none p-0"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}"
                aria-expanded="false"
                aria-controls="{{ $collapseId }}">
            <i class="bi bi-chevron-down debug-thread-card__toggle-icon" aria-hidden="true"></i>
            Pokaż chronologię zdarzeń ({{ $thread['event_count'] ?? 0 }})
        </button>

        <div class="collapse mt-3" id="{{ $collapseId }}">
            <ol class="debug-event-timeline list-unstyled mb-0">
                @foreach($thread['events'] ?? [] as $event)
                    @php
                        $warnings = $eventWarnings[$event->id] ?? [];
                        $metadata = is_array($event->metadata) ? $event->metadata : [];
                        $safeMetadata = $inspector->redacted($metadata);
                        $metaId = $collapseId.'Meta'.$event->id;
                    @endphp
                    <li class="debug-event-timeline__item">
                        <div class="debug-event-timeline__time">
                            {{ $event->formatUtcDatetimeLocal('occurred_at', 'H:i:s') ?? '—' }}
                        </div>
                        <div class="debug-event-timeline__body">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge {{ $eventBadgeClass((string) $event->event_name) }}">{{ $event->event_name }}</span>
                                @if(filled($event->event_category))
                                    <span class="small text-muted">{{ $event->event_category }}</span>
                                @endif
                                @if($warnings !== [])
                                    <span class="badge text-bg-danger">PII</span>
                                @endif
                            </div>
                            @if(filled($event->path))
                                <div class="small"><code>{{ $event->path }}</code></div>
                            @endif
                            @if(filled($event->course_title_snapshot))
                                <div class="small text-muted">{{ $event->course_title_snapshot }}</div>
                            @endif
                            @if(filled($event->referrer_domain) && $loop->first)
                                <div class="small text-muted">Referrer: {{ $event->referrer_domain }}</div>
                            @endif
                            @if($warnings !== [] || $safeMetadata !== [])
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 small"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $metaId }}"
                                        aria-expanded="false">
                                    Metadata
                                </button>
                                <div class="collapse mt-2" id="{{ $metaId }}">
                                    @if($warnings !== [])
                                        <div class="alert alert-danger py-2 px-3 small mb-2">
                                            Niedozwolone klucze: <code>{{ implode(', ', $warnings) }}</code>
                                        </div>
                                    @endif
                                    @if($safeMetadata !== [])
                                        <pre class="debug-metadata-pre mb-0">{{ json_encode($safeMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
</article>
