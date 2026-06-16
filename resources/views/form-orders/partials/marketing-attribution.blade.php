@php
    $variant = $variant ?? 'compact';
    $order = $order ?? $zamowienie ?? null;
@endphp

@if($order && filled($order->fb_source))
    @php
        $campaign = $order->marketingCampaign;
        $sourceType = $campaign?->sourceType;
        $sourceColor = $sourceType?->color ?? '#6c757d';
        $sourceName = $sourceType?->name;
        $campaignCode = (string) $order->fb_source;
        $campaignName = $campaign?->name;
        $utmContent = $order->effectiveMarketingUtmContent();
        $campaignDeleted = $campaign && $campaign->trashed();
        $campaignInactive = $campaign && ! $campaign->is_active && ! $campaignDeleted;
    @endphp

    @if($variant === 'compact')
        @php
            $compactTooltip = $campaign
                ? trim(($sourceName ? $sourceName.' · ' : '').$campaignCode.' — '.($campaignName ?? '').($utmContent ? ' · '.$utmContent : '').($campaignDeleted ? ' (usunięta)' : ''))
                : 'Kod: '.$campaignCode.' — brak kampanii w adm';
            $pillStyle = 'background-color: '.$sourceColor.'1a; color: #374151; border: 1px solid '.$sourceColor.'55;';
        @endphp
        @if($campaign)
            <a href="{{ route('marketing-campaigns.show', $campaign) }}"
               class="badge rounded-pill fw-normal text-decoration-none d-inline-flex align-items-center gap-1 px-2 py-1 form-order-marketing-pill"
               style="{{ $pillStyle }} max-width: 100%;"
               data-bs-toggle="tooltip"
               data-bs-placement="top"
               title="{{ $compactTooltip }}">
                <span class="fw-semibold text-nowrap" style="color: {{ $sourceColor }};">Pozyskano:</span>
                @if($sourceName)
                    <span class="text-truncate" style="max-width: 9rem;">{{ Str::limit($sourceName, 20) }}</span>
                    <span class="opacity-50">·</span>
                @endif
                <code class="text-dark">{{ $campaignCode }}</code>
                @if($campaignName)
                    <span class="text-truncate opacity-75" style="max-width: 11rem;">— {{ Str::limit($campaignName, 32) }}</span>
                @endif
            </a>
        @else
            <span class="badge rounded-pill fw-normal d-inline-flex align-items-center gap-1 px-2 py-1 form-order-marketing-pill"
                  style="{{ $pillStyle }}"
                  data-bs-toggle="tooltip"
                  data-bs-placement="top"
                  title="{{ $compactTooltip }}">
                <span class="fw-semibold text-nowrap" style="color: {{ $sourceColor }};">Pozyskano:</span>
                <code class="text-dark">{{ $campaignCode }}</code>
                <span class="text-warning-emphasis">· nieznana kampania</span>
            </span>
        @endif
    @elseif($variant === 'subtle')
        <p class="small text-muted mb-0 mt-1 form-order-marketing-subtle">
            <i class="bi bi-megaphone opacity-50" aria-hidden="true"></i>
            <span>Pozyskano:</span>
            @if($sourceName)
                <span class="badge rounded-pill fw-normal"
                      style="background-color: {{ $sourceColor }}22; color: {{ $sourceColor }}; border: 1px solid {{ $sourceColor }}44; font-size: 0.7rem;">
                    {{ $sourceName }}
                </span>
            @endif
            @if($campaign)
                <a href="{{ route('marketing-campaigns.show', $campaign) }}"
                   class="text-muted text-decoration-none"
                   title="Podgląd kampanii{{ $campaignDeleted ? ' (usunięta)' : '' }}">
                    <code class="text-muted">{{ $campaignCode }}</code>
                    @if($campaignName)
                        <span>— {{ Str::limit($campaignName, 50) }}</span>
                    @endif
                </a>
                @if($utmContent)
                    <span>· <code class="text-muted">{{ $utmContent }}</code></span>
                @endif
                @if($campaignDeleted)
                    <span class="text-warning-emphasis" title="Kampania usunięta w adm">· usunięta</span>
                @elseif($campaignInactive)
                    <span>· wył.</span>
                @endif
            @else
                <code class="text-muted">{{ $campaignCode }}</code>
                <span class="text-warning-emphasis">· nieznana kampania</span>
            @endif
        </p>
    @endif
@endif

@if($order && filled($order->conversion_placement))
    @php
        $placementLabel = \App\Models\FormOrder::conversionPlacementLabel($order->conversion_placement);
        $placementCode = (string) $order->conversion_placement;
    @endphp

    @if($variant === 'compact')
        <span class="badge rounded-pill fw-normal d-inline-flex align-items-center gap-1 px-2 py-1 form-order-placement-pill"
              style="background-color: #0d6efd14; color: #374151; border: 1px solid #0d6efd44;"
              data-bs-toggle="tooltip"
              data-bs-placement="top"
              title="Konwersja z: {{ $placementLabel }} ({{ $placementCode }})">
            <span class="fw-semibold text-nowrap text-primary">Konwersja:</span>
            <span class="text-truncate" style="max-width: 14rem;">{{ Str::limit($placementLabel, 36) }}</span>
        </span>
    @elseif($variant === 'subtle')
        <p class="small text-muted mb-0 mt-1 form-order-placement-subtle">
            <i class="bi bi-layout-sidebar-inset opacity-50" aria-hidden="true"></i>
            <span>Konwersja z:</span>
            <span class="badge rounded-pill fw-normal"
                  style="background-color: #0d6efd18; color: #0d6efd; border: 1px solid #0d6efd33; font-size: 0.7rem;">
                {{ $placementLabel }}
            </span>
            <code class="text-muted">{{ $placementCode }}</code>
        </p>
    @endif
@endif
