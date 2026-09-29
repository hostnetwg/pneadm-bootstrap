<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Inbox
    </x-slot>

    <div class="container-fluid px-0">
        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h2 class="h4 mb-2">Inbox</h2>
                    <p class="text-secondary mb-0">
                        Pomocniczy widok decyzji. Kliknięcie prowadzi do konkretnego miejsca w projekcie webinaru.
                    </p>
                </div>
                <div class="text-lg-end">
                    <div class="badge bg-warning text-dark mb-2">Etap 0.3.1</div>
                    <div class="small text-secondary">Bez publikacji i wysyłek</div>
                </div>
            </div>
        </section>

        @if(! $project)
            <div class="card border">
                <div class="card-body">
                    <h3 class="h5 mb-2">Inbox jest pusty, bo nie ma projektu webinaru</h3>
                    <p class="text-secondary mb-3">Najpierw zaplanuj webinar TIK. Inbox nie jest głównym flow, tylko listą decyzji wynikających z projektu.</p>
                    <a href="{{ route('growth.projects.create') }}" class="btn btn-primary">Zaplanuj webinar TIK</a>
                </div>
            </div>
        @elseif(count($items) === 0)
            <div class="alert alert-success" role="status">
                Brak decyzji wymagających uwagi w tej sesji.
                <a href="{{ route('growth.projects.show', $project['id']) }}" class="alert-link">Wróć do projektu</a>.
            </div>
        @else
            <p class="small text-secondary mb-3">
                Projekt:
                <a href="{{ route('growth.projects.show', $project['id']) }}">{{ $project['topic'] }}</a>
                · Otwarte: <strong>{{ count($items) }}</strong>
            </p>

            <div class="list-group">
                @foreach($items as $item)
                    <a href="{{ $item['href'] }}" class="list-group-item list-group-item-action">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                            <span class="badge text-bg-secondary">{{ $item['type'] }}</span>
                            <span class="badge text-bg-danger">{{ $item['status'] }}</span>
                        </div>
                        <div class="fw-semibold">{{ $item['title'] }}</div>
                        <div class="small text-secondary mb-2">{{ $item['summary'] }}</div>
                        <span class="small text-primary">Przejdź do miejsca w projekcie →</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
