@php
    $warnings = $eventWarnings[$event->id] ?? [];
    $metadata = is_array($event->metadata) ? $event->metadata : [];
    $safeMetadata = $inspector->redacted($metadata);
    $detailId = 'debugFlatEvent'.$event->id;
    $sessionShort = filled($event->analytics_session_id)
        ? (strlen((string) $event->analytics_session_id) > 8 ? '…'.substr((string) $event->analytics_session_id, -4) : $event->analytics_session_id)
        : '—';

    $contextParts = array_filter([
        filled($event->course_title_snapshot) ? $event->course_title_snapshot : null,
        filled($event->campaign_code) ? 'kamp. '.$event->campaign_code : null,
        filled($event->course_id) ? '#'.$event->course_id : null,
    ]);

    $eventBadgeClass = 'text-bg-light border text-dark';
    if (str_starts_with((string) $event->event_name, 'form_order')) {
        $eventBadgeClass = 'text-bg-success';
    } elseif (str_starts_with((string) $event->event_name, 'order_form')) {
        $eventBadgeClass = 'text-bg-primary';
    } elseif (str_starts_with((string) $event->event_name, 'campaign')) {
        $eventBadgeClass = 'text-bg-info';
    } elseif (str_starts_with((string) $event->event_name, 'course')) {
        $eventBadgeClass = 'text-bg-secondary';
    }
@endphp
<tr class="debug-flat-row">
    <td class="text-nowrap small">{{ $event->formatUtcDatetimeLocal('occurred_at') ?? '' }}</td>
    <td>
        <span class="badge {{ $eventBadgeClass }}">{{ $event->event_name }}</span>
        @if($warnings !== [])
            <span class="badge text-bg-danger ms-1">PII</span>
        @endif
    </td>
    <td><code class="small">{{ $sessionShort }}</code></td>
    <td class="small text-truncate" style="max-width: 200px;" title="{{ implode(' · ', $contextParts) }}">
        {{ $contextParts !== [] ? implode(' · ', $contextParts) : '—' }}
    </td>
    <td class="small"><code>{{ $event->path ?: '—' }}</code></td>
    <td class="text-end">
        <button type="button"
                class="btn btn-sm btn-outline-secondary"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $detailId }}"
                aria-expanded="false">
            Szczegóły
        </button>
    </td>
</tr>
<tr class="collapse debug-flat-detail" id="{{ $detailId }}">
    <td colspan="6" class="bg-light-subtle">
        <div class="p-3">
            <div class="row g-3 small">
                <div class="col-md-4">
                    <div class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.7rem;">Sesja</div>
                    <code class="small d-block text-break">{{ $event->analytics_session_id ?: '—' }}</code>
                    @if(filled($event->order_form_session_id))
                        <div class="mt-2 text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.7rem;">Formularz</div>
                        <code class="small d-block text-break">{{ $event->order_form_session_id }}</code>
                    @endif
                </div>
                <div class="col-md-4">
                    <div class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.7rem;">Kontekst</div>
                    <dl class="mb-0 debug-dl">
                        <dt>Kategoria</dt><dd>{{ $event->event_category ?: '—' }}</dd>
                        <dt>Kurs</dt><dd>{{ $event->course_title_snapshot ?: '—' }} @if($event->course_id)(#{{ $event->course_id }})@endif</dd>
                        <dt>Kampania</dt><dd><code>{{ $event->campaign_code ?: '—' }}</code></dd>
                        <dt>Landing</dt><dd>{{ $event->landing_target ?: '—' }}</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <div class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.7rem;">Źródło / urządzenie</div>
                    <dl class="mb-0 debug-dl">
                        <dt>Referrer</dt><dd>{{ $event->referrer_domain ?: '—' }}</dd>
                        <dt>UTM</dt><dd>{{ trim(($event->utm_source ?: '').' / '.($event->utm_medium ?: '').' / '.($event->utm_campaign ?: ''), ' /') ?: '—' }}</dd>
                        <dt>Route</dt><dd><code>{{ $event->route_name ?: '—' }}</code></dd>
                        <dt>Urządzenie</dt><dd>{{ $event->device_type ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
            @if($warnings !== [])
                <div class="alert alert-danger py-2 px-3 small mt-3 mb-0">
                    Niedozwolone klucze w metadata: <code>{{ implode(', ', $warnings) }}</code>
                </div>
            @endif
            @if($safeMetadata !== [])
                <div class="mt-3">
                    <div class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.7rem;">Metadata JSON</div>
                    <pre class="debug-metadata-pre mb-0">{{ json_encode($safeMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        </div>
    </td>
</tr>
