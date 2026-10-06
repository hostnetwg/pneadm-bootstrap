@php
    $origin = \App\Support\GrowthOS\DemoTikWebinarProject::normalizeAiOrigin($origin ?? null);
    $channel = $channel ?? \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_GENERAL;
    $title = $title ?? 'Ostatnie AI, które ukształtowało ten zapis';
@endphp
@if($origin !== null)
    <span class="badge bg-light text-secondary border" title="{{ $title }}">
        @if(($origin['source'] ?? '') === 'simulation')
            AI (symulacja): {{ \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::badgeForProposal($origin, $channel) }}
        @else
            Wygenerowano: {{ \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::badgeForProposal($origin, $channel) }}
        @endif
    </span>
@endif
