@php
    $urls = $urls ?? ['utm' => '', 'legacy' => '', 'short' => ''];
    $hasLinks = !empty($urls['utm']);
@endphp

<div class="btn-group btn-group-sm campaign-row-actions" role="group" aria-label="Akcje kampanii {{ $campaign->campaign_code }}">
    @if($hasLinks)
        <button type="button"
                class="btn btn-success"
                data-bs-toggle="modal"
                data-bs-target="#campaignLinksModal"
                data-campaign-id="{{ $campaign->id }}"
                data-campaign-code="{{ $campaign->campaign_code }}"
                data-has-links="1"
                data-utm-url="{{ $urls['utm'] }}"
                data-legacy-url="{{ $urls['legacy'] }}"
                data-short-url="{{ $urls['short'] ?? '' }}"
                data-verify-short-link-url="{{ route('marketing-campaigns.verify-short-link', $campaign) }}"
                data-utm-source="{{ $urls['utm_source'] }}"
                data-utm-medium="{{ $urls['utm_medium'] }}"
                data-utm-campaign="{{ $urls['utm_campaign'] }}"
                data-utm-content="{{ $urls['utm_content'] ?? '' }}"
                title="Kopiuj linki kampanii">
            <i class="bi bi-link-45deg"></i>
        </button>
    @endif

    <a href="{{ route('marketing-campaigns.show', $campaign) }}"
       class="btn btn-outline-primary"
       title="Podgląd kampanii">
        <i class="bi bi-eye"></i>
    </a>

    <a href="{{ route('marketing-campaigns.edit', $campaign) }}"
       class="btn btn-outline-warning"
       title="Edytuj kampanię">
        <i class="bi bi-pencil"></i>
    </a>

    <a href="{{ route('marketing-campaigns.duplicate', $campaign) }}"
       class="btn btn-outline-secondary"
       title="Duplikuj kampanię">
        <i class="bi bi-copy"></i>
    </a>

    <button type="button"
            class="btn btn-outline-danger"
            data-bs-toggle="modal"
            data-bs-target="#deleteModal{{ $campaign->id }}"
            title="Usuń kampanię">
        <i class="bi bi-trash"></i>
    </button>
</div>
