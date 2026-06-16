@props([
    'summary' => null,
])

@if(filled($summary))
    <p class="form-text mb-1">{{ $summary }}</p>
@endif

@if(trim($slot) !== '')
    <details class="campaign-field-hint-details small text-muted mb-0">
        <summary>Szczegóły i przykłady</summary>
        <div class="mt-2 ps-2 border-start border-2 border-light-subtle">
            {{ $slot }}
        </div>
    </details>
@endif
