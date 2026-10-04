@if(is_array($proposal))
    @php
        $changedFieldLabels = [
            'title' => 'tytuł',
            'subtitle' => 'podtytuł',
            'promise' => 'obietnica',
            'audience' => 'grupa odbiorców',
            'main_points' => 'główne punkty',
            'agenda' => 'agenda',
            'cta' => 'CTA',
            'additional_material' => 'materiał dodatkowy',
        ];
    @endphp
    <section class="border border-warning rounded p-3 mb-3" aria-labelledby="concept-proposal-heading">
        <h3 class="h6" id="concept-proposal-heading">Propozycja AI</h3>
        <p class="small mb-2">
            <span class="badge text-bg-warning text-dark">{{ $proposal['intent_label'] ?? 'Wariant' }}</span>
            {{ $proposal['note'] ?? '' }}
        </p>
        @if(($proposal['source'] ?? null) === 'real_ai')
            <p class="small text-secondary mb-2">
                AI: {{ ucfirst($proposal['provider'] ?? '') }} / {{ $proposal['model'] ?? '' }}
            </p>
        @endif
        @if(filled($proposal['change_summary'] ?? null))
            <p class="small mb-2 growth-ai-text"><strong>Podsumowanie zmian:</strong> {{ $proposal['change_summary'] }}</p>
        @endif
        @if(!empty($proposal['changed_fields']))
            <p class="small mb-2">
                <strong>Zmienione pola:</strong>
                {{ collect($proposal['changed_fields'])->map(fn ($field) => $changedFieldLabels[$field] ?? $field)->join(', ') }}
            </p>
        @endif
        <p class="fw-semibold mb-1">{{ $proposal['concept']['title'] ?? '' }}</p>
        <p class="small text-secondary mb-2">{{ $proposal['concept']['subtitle'] ?? '' }}</p>
        <p class="small mb-2 growth-ai-text">{{ \App\Support\GrowthOS\AiListFormatter::lineBreaks((string) ($proposal['concept']['promise'] ?? '')) }}</p>
        @if(filled($proposal['concept']['audience'] ?? null))
            <p class="small mb-2 growth-ai-text"><strong>Odbiorcy:</strong> {{ \App\Support\GrowthOS\AiListFormatter::lineBreaks((string) $proposal['concept']['audience']) }}</p>
        @endif
        <ul class="small">
            @foreach(($proposal['concept']['points'] ?? []) as $point)
                <li>{{ $point }}</li>
            @endforeach
        </ul>
        <p class="small mb-2 growth-ai-text"><strong>Agenda:</strong><br>{{ \App\Support\GrowthOS\AiListFormatter::lineBreaks((string) ($proposal['concept']['plan'] ?? '')) }}</p>
        <p class="small mb-3 growth-ai-text"><strong>CTA:</strong> {{ \App\Support\GrowthOS\AiListFormatter::lineBreaks((string) ($proposal['concept']['cta'] ?? '')) }}</p>
        <p class="small mb-3 growth-ai-text"><strong>Materiał dodatkowy:</strong><br>{{ \App\Support\GrowthOS\AiListFormatter::lineBreaks((string) ($proposal['concept']['lead_magnet'] ?? '')) }}</p>
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('growth.projects.concept.ai.apply', $projectId) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">Zastosuj</button>
            </form>
            <form method="POST" action="{{ route('growth.projects.concept.ai.reject', $projectId) }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Odrzuć</button>
            </form>
        </div>
    </section>
@endif
