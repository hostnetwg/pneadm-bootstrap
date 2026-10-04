<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Projekty
    </x-slot>

    <div class="container-fluid px-0">
        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h2 class="h4 mb-2">Projekty</h2>
                    <p class="text-secondary mb-0">Wszystkie Twoje zapisane webinaria. Usunięcie kasuje kampanię, materiały, historię i obrazy na stałe. Propozycja AI zostaje tylko w przeglądarce, w której powstała.</p>
                </div>
                <a href="{{ route('growth.projects.create') }}" class="btn btn-primary align-self-start">
                    Zaplanuj webinar
                </a>
            </div>
        </section>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif

        @if($campaigns !== [])
            <div class="row g-3">
                @foreach($campaigns as $campaign)
                    <div class="col-lg-6">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge text-bg-primary">{{ $projectStatusLabels[$campaign['status']] ?? $campaign['status'] }}</span>
                                    <span class="badge bg-light text-secondary border">Zapisana kampania</span>
                                    @if($campaign['is_open'])
                                        <span class="badge text-bg-success">Otwarty</span>
                                    @endif
                                </div>
                                <h3 class="h5 mb-2">{{ $campaign['topic'] }}</h3>
                                <p class="mb-1"><strong>Live:</strong> {{ $campaign['live_date'] }} {{ $campaign['live_time'] }}</p>
                                <p class="mb-3"><strong>Prowadzący:</strong> {{ $campaign['host'] }}</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('growth.projects.open', $campaign['id']) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary">Otwórz projekt webinaru</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-project-{{ $campaign['id'] }}">
                                        Usuń
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @foreach($campaigns as $campaign)
                <div class="modal fade" id="delete-project-{{ $campaign['id'] }}" tabindex="-1" aria-labelledby="delete-project-{{ $campaign['id'] }}-label" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title h6" id="delete-project-{{ $campaign['id'] }}-label">Usunąć projekt?</h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">Projekt „{{ $campaign['topic'] }}” zostanie usunięty na stałe.</p>
                                <div class="alert alert-warning mb-0" role="alert">
                                    Znikną kampania, kierunek, koncepcja, materiały, historia wersji i obrazy. Tej operacji nie da się cofnąć.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                                <form method="POST" action="{{ route('growth.projects.destroy', $campaign['id']) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Usuń projekt</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
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
