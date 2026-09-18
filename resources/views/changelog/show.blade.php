<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            {{ $changelog['label'] }}
            @if($changelog['version'])
                <span class="badge text-bg-primary align-middle">v {{ $changelog['version'] }}</span>
            @endif
        </h2>
    </x-slot>

    <div class="container py-4" style="max-width: 820px;">
        <p class="text-muted">
            Krótka historia tej aplikacji. Szczegóły zostają w dokumentacji <code>docs/</code>.
        </p>

        @if(! $changelog['readable'])
            <div class="alert alert-warning">
                {{ $changelog['error'] }}
                @if($changelog['path'])
                    <div class="small mt-1">Szukana ścieżka: <code>{{ $changelog['path'] }}</code></div>
                @endif
            </div>
        @endif

        @if($current)
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    Aktualna wersja {{ $current['version'] }}
                    @if($current['date'])
                        <span class="opacity-75">— {{ $current['date'] }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @include('changelog.partials-release', ['release' => $current])
                </div>
            </div>
        @elseif($changelog['readable'])
            <div class="alert alert-secondary">Brak wpisów w historii zmian.</div>
        @endif

        @if(count($history) > 0)
            <h3 class="h5 mb-3">Wcześniejsze wersje</h3>
            @foreach($history as $release)
                <div class="card mb-3">
                    <div class="card-header">
                        {{ $release['version'] }}
                        @if($release['date'])
                            <span class="text-muted">— {{ $release['date'] }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @include('changelog.partials-release', ['release' => $release])
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-app-layout>
