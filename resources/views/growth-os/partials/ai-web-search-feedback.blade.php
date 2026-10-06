{{-- Web-search feedback for an AI proposal (DEC-053). Expects $proposal array. --}}
@php
    $webSearchNote = is_string($proposal['web_search_note'] ?? null) ? trim((string) $proposal['web_search_note']) : '';
    $webSearchUsed = ($proposal['web_search_used'] ?? false) === true;
    $sources = is_array($proposal['sources'] ?? null) ? $proposal['sources'] : [];
@endphp
@if($webSearchNote !== '')
    <div class="alert alert-warning small py-2" role="status">{{ $webSearchNote }}</div>
@elseif(($proposal['web_search_requested'] ?? false) === true && ! $webSearchUsed && ($proposal['source'] ?? '') === 'simulation')
    <div class="alert alert-warning small py-2" role="status">Symulacja lokalna nie sprawdza Internetu.</div>
@endif
@if($webSearchUsed && $sources !== [])
    <h4 class="h6 mt-2">Źródła wykorzystane przez AI</h4>
    <ul class="small mb-2">
        @foreach($sources as $source)
            <li>
                @if(! empty($source['url']))
                    <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['title'] ?: ($source['domain'] ?? $source['url']) }}</a>
                    @if(! empty($source['domain']))
                        <span class="text-secondary">({{ $source['domain'] }})</span>
                    @endif
                @else
                    {{ $source['title'] ?? '' }}
                @endif
            </li>
        @endforeach
    </ul>
@elseif(($proposal['web_search_requested'] ?? false) === true && $webSearchUsed)
    <p class="small text-secondary mb-2">AI skorzystało z wyszukiwania w sieci (bez listy źródeł do pokazania).</p>
@endif
