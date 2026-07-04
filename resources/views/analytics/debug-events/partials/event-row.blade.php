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
