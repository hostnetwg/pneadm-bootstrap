<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Projekt webinaru
    </x-slot>

    <div class="container-fluid px-0">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        <nav class="mb-3 small" aria-label="Okruszki">
            <a href="{{ route('growth.projects.index') }}">Projekty</a>
            <span class="text-secondary"> / </span>
            <span>{{ $project['topic'] }}</span>
        </nav>

        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge text-bg-primary">{{ $projectStatusLabels[$project['status']] ?? $project['status'] }}</span>
                        <span class="badge bg-warning text-dark">Prototyp sesyjny</span>
                    </div>
                    <h2 class="h4 mb-2">{{ $project['topic'] }}</h2>
                    <p class="text-secondary mb-0">
                        {{ $project['type'] }} · {{ $project['live_date'] }} {{ $project['live_time'] }} · {{ $project['host'] }}
                    </p>
                </div>
                <div class="text-lg-end">
                    <div class="fw-semibold">{{ $health['days_label'] }}</div>
                    <div class="{{ $health['critical_count'] > 0 ? 'text-danger' : 'text-success' }}">{{ $health['critical_label'] }}</div>
                </div>
            </div>
        </section>

        <section class="card border-primary mb-4" aria-labelledby="next-action-heading">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1" id="next-action-heading">Najważniejszy następny krok</h2>
                    <p class="text-secondary mb-0">{{ $nextAction['meta'] }}</p>
                </div>
                <a href="{{ $nextAction['href'] }}" class="btn btn-primary align-self-start">
                    {{ $nextAction['label'] }}
                </a>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="stages-heading">
            <h2 class="h5 mb-3" id="stages-heading">Etapy przygotowania</h2>
            <div class="row g-2">
                @foreach($stages as $stage)
                    @php $done = isset($project['completed_steps'][$stage['id']]); @endphp
                    <div class="col-md-4 col-xl-2">
                        <a href="#{{ $stage['anchor'] }}" class="card border text-decoration-none text-reset h-100">
                            <div class="card-body p-3">
                                <span class="badge {{ $done ? 'text-bg-success' : 'bg-light text-secondary border' }}">
                                    {{ $done ? 'Gotowe' : 'Przed nami' }}
                                </span>
                                <div class="fw-semibold mt-2">{{ $stage['label'] }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>

        <section id="direction" class="card border mb-4" aria-labelledby="direction-heading">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <h2 class="h5 mb-0" id="direction-heading">Pomysł i kierunek</h2>
                @if(isset($project['completed_steps']['direction']))
                    <span class="badge text-bg-success">Zatwierdzone w sesji</span>
                @else
                    <span class="badge text-bg-danger">Czeka na decyzję</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Temat</h3>
                        <p>{{ $project['topic'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Dlaczego teraz</h3>
                        <p>{{ $project['direction']['why_now'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Dla kogo</h3>
                        <p>{{ $project['direction']['audience'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Jaki problem rozwiązujemy</h3>
                        <p>{{ $project['direction']['problem'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Co uczestnik ma wynieść</h3>
                        <p>{{ $project['direction']['takeaway'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Czy chcemy coś później sprzedawać</h3>
                        <p>{{ $project['direction']['sell_later'] }}</p>
                    </div>
                </div>
                <div class="alert alert-info small mb-3">
                    Sugestia AI: temat jest aktualny, bo łączy AI z konkretną pracą nauczyciela. Komunikację prowadź przez oszczędność czasu, nie przez technologiczną modę.
                </div>
                @unless(isset($project['completed_steps']['direction']))
                    <form method="POST" action="{{ route('growth.projects.steps.complete', [$project['id'], 'direction']) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Zatwierdź kierunek</button>
                    </form>
                @endunless
            </div>
        </section>

        <section id="concept" class="card border mb-4" aria-labelledby="concept-heading">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <h2 class="h5 mb-0" id="concept-heading">Koncepcja webinaru</h2>
                @if(isset($project['completed_steps']['concept']))
                    <span class="badge text-bg-success">Gotowe w sesji</span>
                @else
                    <span class="badge text-bg-danger">Do dopracowania</span>
                @endif
            </div>
            <div class="card-body">
                <h3 class="h6 text-secondary">Tytuł webinaru</h3>
                <p class="h5">{{ $project['concept']['title'] }}</p>
                <p><strong>Podtytuł:</strong> {{ $project['concept']['subtitle'] }}</p>
                <p><strong>Główna obietnica:</strong> {{ $project['concept']['promise'] }}</p>
                <h3 class="h6 text-secondary">3-5 głównych punktów</h3>
                <ul>
                    @foreach($project['concept']['points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
                <p><strong>Plan webinaru:</strong> {{ $project['concept']['plan'] }}</p>
                <p><strong>Główne CTA:</strong> {{ $project['concept']['cta'] }}</p>
                <p><strong>Materiał dodatkowy / lead magnet:</strong> {{ $project['concept']['lead_magnet'] }}</p>
                <p><strong>Czy prowadzi do produktu:</strong> {{ $project['concept']['next_product'] }}</p>
                <div class="alert alert-info small mb-3">
                    Sugestia AI: przygotuj 2 warianty tytułu - jeden praktyczny, drugi bardziej społecznościowy. Na tym etapie nic nie jest publikowane.
                </div>
                @unless(isset($project['completed_steps']['concept']))
                    <form method="POST" action="{{ route('growth.projects.steps.complete', [$project['id'], 'concept']) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Koncepcja gotowa</button>
                    </form>
                @endunless
            </div>
        </section>

        <section id="materials" class="mb-4" aria-labelledby="materials-heading">
            <h2 class="h5 mb-3" id="materials-heading">Materiały</h2>
            <div class="list-group">
                @foreach($project['materials'] as $material)
                    <a href="{{ route('growth.projects.materials.show', [$project['id'], $material['id']]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-3">
                        <span>
                            <span class="badge text-bg-secondary me-2">{{ $material['kind'] }}</span>
                            <span class="fw-semibold">{{ $material['name'] }}</span>
                            <span class="d-block small text-secondary mt-1">{{ $material['summary'] }}</span>
                        </span>
                        <span class="badge bg-light text-secondary border text-nowrap">
                            {{ $materialStatusLabels[$material['status']] ?? $material['status'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section id="timeline" class="mb-4" aria-labelledby="timeline-heading">
            <h2 class="h5 mb-3" id="timeline-heading">Checklista czasowa</h2>
            <div class="row g-3">
                @foreach($timeline as $group)
                    <div class="col-md-6 col-xl-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h3 class="h6">{{ $group['period'] }}</h3>
                                <ul class="small mb-0">
                                    @foreach($group['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
