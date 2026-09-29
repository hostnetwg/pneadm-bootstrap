<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Dzisiaj
    </x-slot>

    <div class="container-fluid px-0">
        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h2 class="h4 mb-2">Dzisiaj</h2>
                    <p class="text-secondary mb-0">Operacyjny widok producenta: co powinienem zrobić teraz?</p>
                </div>
                <span class="badge bg-warning text-dark align-self-start align-self-lg-center">
                    Etap 0.3.1 · prototyp sesyjny
                </span>
            </div>
        </section>

        <section class="card border-primary mb-4" aria-labelledby="next-action-heading">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h2 class="h5 mb-2" id="next-action-heading">Najważniejszy następny krok</h2>
                        <p class="text-secondary mb-0">{{ $nextAction['meta'] }}</p>
                    </div>
                    <div class="text-lg-end">
                        <a href="{{ $nextAction['href'] }}" class="btn btn-primary">
                            {{ $nextAction['label'] }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="today-important-heading">
            <h2 class="h5 mb-3" id="today-important-heading">Najważniejsze</h2>

            @if($project)
                <div class="card border">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge text-bg-primary">{{ $projectStatusLabels[$project['status']] ?? $project['status'] }}</span>
                            <span class="badge bg-light text-secondary border">Dane demonstracyjne</span>
                        </div>
                        <h3 class="h5 mb-2">TIK — {{ $project['live_date'] }} {{ $project['live_time'] }}</h3>
                        <p class="mb-1"><strong>Temat:</strong> {{ $project['topic'] }}</p>
                        <p class="mb-1"><strong>Prowadzący:</strong> {{ $project['host'] }}</p>
                        <p class="mb-3 text-secondary">
                            {{ $health['days_label'] }}
                            <span class="{{ $health['critical_count'] > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $health['critical_label'] }}
                            </span>
                        </p>
                        <a href="{{ route('growth.projects.show', $project['id']) }}" class="btn btn-outline-primary">
                            Kontynuuj przygotowanie
                        </a>
                    </div>
                </div>
            @else
                <div class="card border">
                    <div class="card-body">
                        <h3 class="h5 mb-2">Nie ma jeszcze projektu webinaru TIK</h3>
                        <p class="text-secondary mb-3">
                            Zacznij od prostego planu: data live, prowadzący, cel i temat. Prototyp zapisze to tylko w sesji.
                        </p>
                        <a href="{{ route('growth.projects.create') }}" class="btn btn-primary">
                            Zaplanuj webinar TIK
                        </a>
                    </div>
                </div>
            @endif
        </section>

        <div class="row g-4">
            <div class="col-md-6">
                <section class="card border h-100" aria-labelledby="today-decisions-heading">
                    <div class="card-body">
                        <h2 class="h6 text-secondary" id="today-decisions-heading">Decyzje</h2>
                        <p class="display-6 mb-2">{{ $inboxCount }}</p>
                        <p class="text-secondary mb-3">wymagają uwagi w Inboxie</p>
                        <a href="{{ route('growth.inbox.index') }}" class="btn btn-sm btn-outline-primary">Otwórz Inbox</a>
                    </div>
                </section>
            </div>
            <div class="col-md-6">
                <section class="card border h-100" aria-labelledby="today-ideas-heading">
                    <div class="card-body">
                        <h2 class="h6 text-secondary" id="today-ideas-heading">Pomysły</h2>
                        <p class="display-6 mb-2">{{ $ideasCount }}</p>
                        <p class="text-secondary mb-3">symulowane sugestie tematów TIK</p>
                        <a href="{{ route('growth.ideas.index') }}" class="btn btn-sm btn-outline-primary">Zobacz pomysły</a>
                    </div>
                </section>
            </div>
        </div>

        <section class="mt-4" aria-labelledby="growth-note-heading">
            <div class="alert alert-info mb-0" role="note">
                <h2 class="h6" id="growth-note-heading">Granice prototypu</h2>
                <p class="mb-0 small">
                    To nie jest Course, MarketingCampaign ani prawdziwa wysyłka. Wszystko działa tylko w sesji HTTP.
                    AI jest symulowane, a publikacje i maile nie są uruchamiane.
                </p>
            </div>
        </section>
    </div>
</x-app-layout>
