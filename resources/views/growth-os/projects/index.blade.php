<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Projekty
    </x-slot>

    <div class="container-fluid px-0">
        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h2 class="h4 mb-2">Projekty</h2>
                    <p class="text-secondary mb-0">Na etapie 0.3 projekt webinaru istnieje tylko w Twojej sesji.</p>
                </div>
                <a href="{{ route('growth.projects.create') }}" class="btn btn-primary align-self-start">
                    Zaplanuj webinar TIK
                </a>
            </div>
        </section>

        @if($project)
            <div class="card border">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge text-bg-primary">{{ $projectStatusLabels[$project['status']] ?? $project['status'] }}</span>
                        <span class="badge bg-light text-secondary border">Sesja</span>
                    </div>
                    <h3 class="h5 mb-2">{{ $project['topic'] }}</h3>
                    <p class="mb-1"><strong>Live:</strong> {{ $project['live_date'] }} {{ $project['live_time'] }}</p>
                    <p class="mb-3"><strong>Prowadzący:</strong> {{ $project['host'] }}</p>
                    <a href="{{ route('growth.projects.show', $project['id']) }}" class="btn btn-outline-primary">
                        Otwórz projekt webinaru
                    </a>
                </div>
            </div>
        @else
            <div class="card border">
                <div class="card-body">
                    <h3 class="h5 mb-2">Brak projektu webinaru</h3>
                    <p class="text-secondary mb-3">{{ $nextAction['meta'] }}</p>
                    <a href="{{ $nextAction['href'] }}" class="btn btn-primary">{{ $nextAction['label'] }}</a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
