@php
    $metric = $comparison['metrics'][$metricKey] ?? null;
@endphp
@if($metric !== null)
    @php
        $delta = $metric['delta'] ?? null;
        $isRate = ($type ?? 'count') === 'rate';
        $hasDelta = $delta !== null && (float) $delta !== 0.0;
        $positive = $hasDelta && (float) $delta > 0;
        $negative = $hasDelta && (float) $delta < 0;
        $colorClass = $positive ? 'text-success' : ($negative ? 'text-danger' : 'text-muted');
        $arrow = $positive ? '↑' : ($negative ? '↓' : '→');
    @endphp
    <div class="small {{ $colorClass }} mt-1" title="Poprzedni okres: {{ $comparison['previous_period']['date_from'] ?? '—' }} – {{ $comparison['previous_period']['date_to'] ?? '—' }}">
        <span aria-hidden="true">{{ $arrow }}</span>
        @if($isRate)
            {{ $delta !== null ? (($delta > 0 ? '+' : '').number_format((float) $delta, 2, ',', ' ').' pp') : '—' }}
        @elseif(($type ?? 'count') === 'money')
            {{ $delta !== null ? (($delta > 0 ? '+' : '').number_format((float) $delta, 2, ',', ' ').' PLN') : '—' }}
            @if(($metric['delta_percent'] ?? null) !== null)
                <span class="text-muted">({{ ($metric['delta_percent'] > 0 ? '+' : '').number_format((float) $metric['delta_percent'], 1, ',', ' ') }}%)</span>
            @endif
        @else
            {{ $delta !== null ? (($delta > 0 ? '+' : '').number_format((float) $delta, 0, ',', ' ')) : '—' }}
            @if(!$isRate && ($metric['delta_percent'] ?? null) !== null)
                <span class="text-muted">({{ ($metric['delta_percent'] > 0 ? '+' : '').number_format((float) $metric['delta_percent'], 1, ',', ' ') }}%)</span>
            @endif
        @endif
        <span class="text-muted">vs poprz. okres</span>
    </div>
@endif
